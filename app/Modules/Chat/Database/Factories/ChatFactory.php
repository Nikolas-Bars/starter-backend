<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Enums\ChatTypeEnum;
use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chat>
 */
final class ChatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ChatTypeEnum::Direct,
        ];
    }

    /**
     * Личный чат двух пользователей вместе с участниками
     */
    public function between(User $first, User $second): static
    {
        return $this
            ->state(['direct_key' => Chat::directKey($first->id, $second->id)])
            ->afterCreating(static function (Chat $chat) use ($first, $second): void {
                ChatMember::factory()->create(['chat_id' => $chat->id, 'user_id' => $first->id]);
                ChatMember::factory()->create(['chat_id' => $chat->id, 'user_id' => $second->id]);
            });
    }
}
