<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMember>
 */
final class ChatMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_id'              => Chat::factory(),
            'user_id'              => User::factory(),
            'last_read_message_id' => 0,
        ];
    }
}
