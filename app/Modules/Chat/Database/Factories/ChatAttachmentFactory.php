<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatAttachment>
 */
final class ChatAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'message_id'    => null,
            'kind'          => ChatAttachmentKindEnum::File,
            'status'        => ChatAttachmentStatusEnum::Ready,
            'original_name' => 'document.pdf',
            'mime'          => 'application/pdf',
            'size'          => 1024,
            'path'          => 'files/' . Str::random(32) . '.bin',
        ];
    }

    public function image(): static
    {
        return $this->state(fn(): array => [
            'kind'          => ChatAttachmentKindEnum::Image,
            'original_name' => 'photo.jpg',
            'mime'          => 'image/jpeg',
            'path'          => 'files/' . Str::random(32) . '.jpg',
            'thumb_path'    => 'files/' . Str::random(32) . '-thumb.jpg',
            'width'         => 1280,
            'height'        => 960,
        ]);
    }

    public function processing(): static
    {
        return $this->state(['status' => ChatAttachmentStatusEnum::Processing]);
    }
}
