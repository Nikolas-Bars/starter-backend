<?php

declare(strict_types=1);

namespace App\Services;

use Imagick;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Сжатие вложений перед отправкой: фото через Imagick, видео и голосовые через ffmpeg.
 * У результата нет метаданных исходника — ни координат, ни модели телефона.
 *
 * @phpstan-type ImageResult array{mime: string, width: int, height: int}
 * @phpstan-type VideoResult array{width: int, height: int, duration_ms: int}
 * @phpstan-type VoiceResult array{duration_ms: int, waveform: list<int>}
 */
final readonly class MediaProcessor
{
    // Дальше фото не открываем: распакованное в память оно займёт гигабайты
    private const int MAX_IMAGE_PIXELS = 100_000_000;

    private const int IMAGICK_MEMORY_BYTES = 256 * 1024 * 1024;

    /**
     * @param array{max_side: int, thumb_side: int, quality: int, thumb_quality: int} $image
     * @param array{max_side: int, crf: int, audio_kbps: int, threads: int}           $video
     * @param array{audio_kbps: int, waveform_peaks: int}                             $voice
     * @param array{side: int, quality: int}                                          $avatar
     */
    public function __construct(
        private string $ffmpeg,
        private string $ffprobe,
        private int $timeoutSeconds,
        private array $image,
        private array $video,
        private array $voice,
        private array $avatar,
    ) {
    }

    /**
     * PNG остаётся PNG (прозрачность, скриншоты), GIF — как есть (анимация), остальное — JPEG.
     *
     * @return ImageResult
     */
    public function image(string $source, string $sourceMime, string $target, string $thumb): array
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::IMAGICK_MEMORY_BYTES);

        $ping = new Imagick();
        $ping->pingImage($source);

        if ($ping->getImageWidth() * $ping->getImageHeight() > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('Слишком большое фото');
        }

        $ping->clear();

        // Из многокадровых (HEIC с серией, анимированный GIF) берём первый кадр
        $image = new Imagick($source . '[0]');
        $this->autoOrient($image);
        $image->stripImage();

        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        if ($sourceMime === 'image/gif') {
            if (!\copy($source, $target)) {
                throw new RuntimeException('Не удалось сохранить GIF');
            }

            $mime = 'image/gif';
        } else {
            $mime = $sourceMime === 'image/png' ? 'image/png' : 'image/jpeg';
            $main = clone $image;
            $this->fit($main, $this->image['max_side']);
            $this->write($main, $mime, $target, $this->image['quality']);
            $main->clear();
        }

        $this->fit($image, $this->image['thumb_side']);
        $this->write($image, 'image/jpeg', $thumb, $this->image['thumb_quality']);

        $size = $this->imageSize($target);
        $image->clear();

        return ['mime' => $mime, 'width' => $size[0], 'height' => $size[1]];
    }

    /**
     * Аватарка: квадрат по центру, JPEG без метаданных
     */
    public function avatar(string $source, string $target): void
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::IMAGICK_MEMORY_BYTES);

        $ping = new Imagick();
        $ping->pingImage($source);

        if ($ping->getImageWidth() * $ping->getImageHeight() > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('Слишком большое фото');
        }

        $ping->clear();

        $image = new Imagick($source . '[0]');
        $this->autoOrient($image);

        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        $image->cropThumbnailImage($this->avatar['side'], $this->avatar['side']);
        $image->setImagePage(0, 0, 0, 0);
        $this->write($image, 'image/jpeg', $target, $this->avatar['quality']);
        $image->clear();
    }

    /**
     * H.264 + AAC в mp4 не больше 1280 по длинной стороне: играется везде и сразу, пока докачивается.
     *
     * @return VideoResult
     */
    public function video(string $source, string $target, string $thumb): array
    {
        $side = $this->video['max_side'];

        $this->run([
            $this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
            '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-map_metadata', '-1', '-map_chapters', '-1',
            '-vf', "scale=w='min({$side},iw)':h='min({$side},ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,format=yuv420p",
            '-fpsmax', '30',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', (string)$this->video['crf'], '-profile:v', 'high',
            '-c:a', 'aac', '-b:a', $this->video['audio_kbps'] . 'k', '-ac', '2',
            '-movflags', '+faststart',
            '-threads', (string)$this->video['threads'],
            '-f', 'mp4', $target,
        ]);

        $probe       = $this->probe($target);
        $durationMs  = $probe['duration_ms'];
        $posterAtSec = \min(1.0, $durationMs / 2000);

        $this->run([
            $this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
            '-ss', \sprintf('%.2F', $posterAtSec), '-i', $target,
            '-frames:v', '1',
            '-vf', "scale={$this->image['thumb_side']}:{$this->image['thumb_side']}:force_original_aspect_ratio=decrease",
            '-q:v', '4', '-f', 'image2', '-c:v', 'mjpeg', $thumb,
        ]);

        if ($probe['width'] === 0 || $probe['height'] === 0) {
            throw new RuntimeException('В видео нет картинки');
        }

        return ['width' => $probe['width'], 'height' => $probe['height'], 'duration_ms' => $durationMs];
    }

    /**
     * Голосовое: моно AAC в m4a и полоска громкости для плеера.
     *
     * @return VoiceResult
     */
    public function voice(string $source, string $target): array
    {
        $this->run([
            $this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
            '-i', $source,
            '-vn', '-map', '0:a:0', '-map_metadata', '-1',
            '-ac', '1', '-c:a', 'aac', '-b:a', $this->voice['audio_kbps'] . 'k',
            '-movflags', '+faststart', '-f', 'mp4', $target,
        ]);

        $durationMs = $this->probe($target)['duration_ms'];

        return [
            'duration_ms' => $durationMs,
            'waveform'    => $this->waveform($target, $durationMs, $this->voice['waveform_peaks']),
        ];
    }

    /**
     * Пиковая громкость по равным отрезкам, 0–100 относительно самого громкого.
     * Звук читается потоком: часовое голосовое целиком в память не поместится.
     *
     * @return list<int>
     */
    private function waveform(string $path, int $durationMs, int $peaks): array
    {
        $rate    = 8000;
        $total   = \max(1, \intdiv($durationMs * $rate, 1000));
        $levels  = \array_map(static fn(): int => 0, \range(1, $peaks));
        $index   = 0;
        $pending = '';

        $process = $this->process([
            $this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin',
            '-i', $path, '-ac', '1', '-ar', (string)$rate, '-f', 's16le', '-',
        ]);
        $process->start();

        foreach ($process->getIterator(Process::ITER_SKIP_ERR) as $chunk) {
            $pending .= $chunk;
            $usable  = \strlen($pending) - \strlen($pending) % 2;
            $samples = $usable > 0 ? \unpack('v*', \substr($pending, 0, $usable)) : [];
            $pending = \substr($pending, $usable);

            foreach ($samples === false ? [] : $samples as $sample) {
                $sample = \is_int($sample) ? $sample : 0;
                $level  = $sample >= 0x8000 ? 0x10000 - $sample : $sample;
                $bucket = \min($peaks - 1, \intdiv($index * $peaks, $total));

                if ($level > $levels[$bucket]) {
                    $levels[$bucket] = $level;
                }

                ++$index;
            }
        }

        if ($process->wait() !== 0) {
            throw new RuntimeException('ffmpeg: ' . \mb_substr(\trim($process->getErrorOutput()), 0, 500));
        }

        $loudest = \max(1, ...$levels);

        return \array_map(static fn(int $level): int => (int)\round($level * 100 / $loudest), $levels);
    }

    /**
     * Камера пишет кадр как есть и отмечает поворот в EXIF; после strip метки не будет, поэтому поворачиваем сами.
     */
    private function autoOrient(Imagick $image): void
    {
        match ($image->getImageOrientation()) {
            Imagick::ORIENTATION_TOPRIGHT    => $image->flopImage(),
            Imagick::ORIENTATION_BOTTOMRIGHT => $image->rotateImage('#000', 180),
            Imagick::ORIENTATION_BOTTOMLEFT  => $image->flipImage(),
            Imagick::ORIENTATION_LEFTTOP     => $image->transposeImage(),
            Imagick::ORIENTATION_RIGHTTOP    => $image->rotateImage('#000', 90),
            Imagick::ORIENTATION_RIGHTBOTTOM => $image->transverseImage(),
            Imagick::ORIENTATION_LEFTBOTTOM  => $image->rotateImage('#000', -90),
            default                          => true,
        };

        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private function fit(Imagick $image, int $side): void
    {
        if ($image->getImageWidth() > $side || $image->getImageHeight() > $side) {
            $image->resizeImage($side, $side, Imagick::FILTER_LANCZOS, 1, true);
        }
    }

    private function write(Imagick $image, string $mime, string $path, int $quality): void
    {
        if ($mime === 'image/jpeg') {
            if ($image->getImageAlphaChannel()) {
                $image->setImageBackgroundColor('white');
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }

            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality($quality);
            $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        } else {
            $image->setImageFormat('png');
        }

        $image->stripImage();
        $image->writeImage($path);
    }

    /**
     * @return array{int, int}
     */
    private function imageSize(string $path): array
    {
        $size = \getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('Не удалось прочитать результат');
        }

        return [$size[0], $size[1]];
    }

    /**
     * @return array{width: int, height: int, duration_ms: int}
     */
    private function probe(string $path): array
    {
        $output = $this->run([
            $this->ffprobe, '-v', 'error',
            '-show_entries', 'stream=codec_type,width,height:format=duration',
            '-of', 'json', $path,
        ]);

        $probe = \json_decode($output, true);

        if (!\is_array($probe)) {
            throw new RuntimeException('ffprobe вернул не JSON');
        }

        $width   = 0;
        $height  = 0;
        $streams = \is_array($probe['streams'] ?? null) ? $probe['streams'] : [];

        foreach ($streams as $stream) {
            if (\is_array($stream) && ($stream['codec_type'] ?? null) === 'video') {
                $width  = \is_int($stream['width'] ?? null) ? $stream['width'] : 0;
                $height = \is_int($stream['height'] ?? null) ? $stream['height'] : 0;

                break;
            }
        }

        $format   = \is_array($probe['format'] ?? null) ? $probe['format'] : [];
        $duration = \is_numeric($format['duration'] ?? null) ? (float)$format['duration'] : 0.0;

        return ['width' => $width, 'height' => $height, 'duration_ms' => (int)\round($duration * 1000)];
    }

    /**
     * Сжатие не должно отбирать процессор у звонков: идёт с пониженным приоритетом.
     *
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $process = $this->process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('ffmpeg: ' . \mb_substr(\trim($process->getErrorOutput()), 0, 500));
        }

        return $process->getOutput();
    }

    /**
     * @param list<string> $command
     */
    private function process(array $command): Process
    {
        if (\PHP_OS_FAMILY === 'Linux') {
            \array_unshift($command, 'nice', '-n', '10');
        }

        return new Process($command, null, null, null, $this->timeoutSeconds);
    }
}
