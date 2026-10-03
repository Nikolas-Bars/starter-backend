<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Jobs\ProcessChatAttachmentJob;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use App\Services\RealtimeBus;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Сжатие по-настоящему: нужны Imagick и ffmpeg (есть в Docker-образе и в CI)
 */
final class ProcessChatAttachmentJobTest extends TestCase
{
    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('attachments');
    }

    public function testPhotoLosesLocationAndIsTurnedUpright(): void
    {
        $source = '2026/10/photo.src';
        $this->disk->put($source, $this->jpegWithExif(64, 32));
        $before = new Imagick($this->disk->path($source));
        self::assertSame('N', $before->getImageProperty('exif:GPSLatitudeRef'));
        self::assertSame(Imagick::ORIENTATION_RIGHTTOP, $before->getImageOrientation());

        $photo = $this->attachment(ChatAttachmentKindEnum::Image, 'image/jpeg', $source);
        dispatch(new ProcessChatAttachmentJob($photo->id));

        $photo->refresh();
        self::assertSame(ChatAttachmentStatusEnum::Ready, $photo->status);
        self::assertSame('2026/10/photo.jpg', $photo->path);
        self::assertSame('2026/10/photo-thumb.jpg', $photo->thumb_path);
        self::assertSame('image/jpeg', $photo->mime);
        // Камера сняла 64×32 и отметила поворот на 90°: в чате фото стоит как надо
        self::assertSame([32, 64], [$photo->width, $photo->height]);
        $this->disk->assertMissing($source);
        self::assertSame($this->disk->size($photo->path) + $this->disk->size($photo->thumb_path), $photo->size);

        $after = new Imagick($this->disk->path($photo->path));
        self::assertSame([], $after->getImageProperties('exif:*'));
        self::assertSame(Imagick::ORIENTATION_UNDEFINED, $after->getImageOrientation());
    }

    public function testLargePhotoIsShrunkAndPngStaysPng(): void
    {
        $source = '2026/10/screen.src';
        $image  = new Imagick();
        $image->newImage(3000, 1500, new ImagickPixel('transparent'), 'png');
        $this->disk->put($source, $image->getImageBlob());

        $screen = $this->attachment(ChatAttachmentKindEnum::Image, 'image/png', $source);
        dispatch(new ProcessChatAttachmentJob($screen->id));

        $screen->refresh();
        self::assertSame('2026/10/screen.png', $screen->path);
        self::assertSame('image/png', $screen->mime);
        self::assertSame([2560, 1280], [$screen->width, $screen->height]);
        self::assertSame([480, 240], \array_slice((array)\getimagesize($this->disk->path((string)$screen->thumb_path)), 0, 2));
    }

    public function testVideoIsCompressedAndReadyFileGoesToChat(): void
    {
        $me      = User::factory()->create();
        $ivan    = User::factory()->create();
        $chat    = Chat::factory()->between($me, $ivan)->create();
        $message = ChatMessage::factory()->inChat($chat, $me)->create(['body' => '']);
        $source  = '2026/10/clip.src';
        $this->ffmpeg(['-f', 'lavfi', '-i', 'testsrc=size=1920x1080:rate=30:duration=2', '-f', 'lavfi', '-i', 'sine=duration=2', '-c:v', 'libx264', '-c:a', 'aac', '-shortest', '-f', 'mov'], $source);

        $clip = $this->attachment(ChatAttachmentKindEnum::Video, 'video/quicktime', $source, $me, $message);
        dispatch(new ProcessChatAttachmentJob($clip->id));

        $clip->refresh();
        self::assertSame(ChatAttachmentStatusEnum::Ready, $clip->status);
        self::assertSame('2026/10/clip.mp4', $clip->path);
        self::assertSame('video/mp4', $clip->mime);
        self::assertSame([1280, 720], [$clip->width, $clip->height]);
        self::assertEqualsWithDelta(2000, (int)$clip->duration_ms, 100);
        $this->disk->assertExists((string)$clip->thumb_path);
        $this->disk->assertMissing($source);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertCount(1, $events);
        self::assertEqualsCanonicalizing([$me->id, $ivan->id], $events[0]['user_ids']);
        self::assertSame('chat.attachment', $events[0]['message']['type']);
        self::assertSame($chat->id, $events[0]['message']['data']['chat_id']);
        self::assertSame($message->id, $events[0]['message']['data']['message_id']);
        self::assertSame('ready', $events[0]['message']['data']['attachment']['status']);
    }

    public function testVoiceGetsWaveform(): void
    {
        $source = '2026/10/voice.src';
        $this->ffmpeg(['-f', 'lavfi', '-i', 'sine=frequency=440:duration=1.5', '-c:a', 'libopus', '-f', 'webm'], $source);

        $voice = $this->attachment(ChatAttachmentKindEnum::Voice, 'video/webm', $source);
        dispatch(new ProcessChatAttachmentJob($voice->id));

        $voice->refresh();
        self::assertSame(ChatAttachmentStatusEnum::Ready, $voice->status);
        self::assertSame('2026/10/voice.m4a', $voice->path);
        self::assertSame('audio/mp4', $voice->mime);
        self::assertNull($voice->thumb_path);
        self::assertEqualsWithDelta(1500, (int)$voice->duration_ms, 100);
        self::assertIsArray($voice->waveform);
        self::assertCount(64, $voice->waveform);
        self::assertSame(100, \max($voice->waveform));

        // Не отправлено — рассылать некому
        self::assertSame([], $this->app->make(RealtimeBus::class)->drain(10));
    }

    public function testBrokenFileIsMarkedFailed(): void
    {
        $source = '2026/10/broken.src';
        $this->disk->put($source, 'это не видео');

        $broken = $this->attachment(ChatAttachmentKindEnum::Video, 'video/mp4', $source);
        dispatch(new ProcessChatAttachmentJob($broken->id));

        $broken->refresh();
        self::assertSame(ChatAttachmentStatusEnum::Failed, $broken->status);
        self::assertSame(0, $broken->size);
        self::assertSame([], $this->disk->allFiles());
    }

    private function attachment(ChatAttachmentKindEnum $kind, string $mime, string $path, ?User $user = null, ?ChatMessage $message = null): ChatAttachment
    {
        return ChatAttachment::factory()->processing()->create([
            'user_id'    => $user->id ?? User::factory()->create()->id,
            'message_id' => $message?->id,
            'kind'       => $kind,
            'mime'       => $mime,
            'path'       => $path,
            'size'       => $this->disk->size($path),
        ]);
    }

    /**
     * @param list<string> $arguments
     */
    private function ffmpeg(array $arguments, string $path): void
    {
        $this->disk->makeDirectory(\dirname($path));
        $process = new Process(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', ...$arguments, $this->disk->path($path)]);
        $process->mustRun();
    }

    /**
     * JPEG, как с телефона: в EXIF координаты и поворот на 90° (Orientation = 6)
     */
    private function jpegWithExif(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('red'), 'jpeg');
        $jpeg = $image->getImageBlob();

        // TIFF little-endian: IFD0 с Orientation и ссылкой на GPS IFD, в GPS IFD — GPSLatitudeRef = N
        $tiff = "II*\x00" . \pack('V', 8)
            . \pack('v', 2)
            . \pack('vvV', 0x0112, 3, 1) . \pack('vv', 6, 0)
            . \pack('vvVV', 0x8825, 4, 1, 38)
            . \pack('V', 0)
            . \pack('v', 1)
            . \pack('vvV', 0x0001, 2, 2) . "N\x00\x00\x00"
            . \pack('V', 0);
        $exif = "Exif\x00\x00" . $tiff;

        return \substr($jpeg, 0, 2) . "\xFF\xE1" . \pack('n', \strlen($exif) + 2) . $exif . \substr($jpeg, 2);
    }
}
