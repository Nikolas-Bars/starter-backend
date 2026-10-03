<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Services\FileUrlSigner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ChatAttachmentResource",
 *     type="object",
 *     description="Файл в сообщении. Ссылки относительные, подписанные и действуют до конца следующих суток (UTC)",
 *     @OA\Property(property="id", type="integer", example=5),
 *     @OA\Property(property="kind", type="string", enum={"image", "video", "voice", "file"}),
 *     @OA\Property(property="status", type="string", enum={"processing", "ready", "failed"}, description="processing — ещё сжимается: придёт событие chat.attachment"),
 *     @OA\Property(property="name", type="string", example="IMG_2041.HEIC", description="Имя у отправителя"),
 *     @OA\Property(property="mime", type="string", example="image/jpeg"),
 *     @OA\Property(property="size", type="integer", example=482113, description="Байт"),
 *     @OA\Property(property="width", type="integer", nullable=true, example=1920),
 *     @OA\Property(property="height", type="integer", nullable=true, example=1440),
 *     @OA\Property(property="duration_ms", type="integer", nullable=true, description="Видео и голосовые"),
 *     @OA\Property(property="waveform", type="array", nullable=true, description="Голосовые: громкость по отрезкам, 0–100", @OA\Items(type="integer")),
 *     @OA\Property(property="url", type="string", nullable=true, example="/api/files/2026/10/abc.jpg?expires=1791072000&signature=…", description="null, пока не готов"),
 *     @OA\Property(property="thumb_url", type="string", nullable=true, description="Превью фото и кадр видео")
 * )
 *
 * @mixin ChatAttachment
 */
final class ChatAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $signer = resolve(FileUrlSigner::class);
        $ready  = $this->status === ChatAttachmentStatusEnum::Ready;

        return [
            'id'          => $this->id,
            'kind'        => $this->kind->value,
            'status'      => $this->status->value,
            'name'        => $this->original_name,
            'mime'        => $this->mime,
            'size'        => $this->size,
            'width'       => $this->width,
            'height'      => $this->height,
            'duration_ms' => $this->duration_ms,
            'waveform'    => $this->waveform,
            'url'         => $ready ? $signer->url($this->path) : null,
            'thumb_url'   => $ready && $this->thumb_path !== null ? $signer->url($this->thumb_path) : null,
        ];
    }
}
