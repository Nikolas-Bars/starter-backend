<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
final class ChatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_id'   => Chat::factory(),
            'user_id'   => User::factory(),
            'client_id' => Str::uuid()->toString(),
            'body'      => fake()->sentence(),
        ];
    }

    /**
     * Сообщение в чате: заодно становится последним сообщением чата
     */
    public function inChat(Chat $chat, User $author): static
    {
        return $this
            ->state(['chat_id' => $chat->id, 'user_id' => $author->id])
            ->afterCreating(static function (ChatMessage $message) use ($chat): void {
                $chat->update(['last_message_id' => $message->id]);
            });
    }
}
