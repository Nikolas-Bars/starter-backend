<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatFolder;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatFolder>
 */
final class ChatFolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name'    => fake()->unique()->word(),
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    /**
     * Папка сразу с этими чатами
     */
    public function withChats(Chat ...$chats): static
    {
        return $this->afterCreating(static function (ChatFolder $folder) use ($chats): void {
            $folder->chats()->attach(\array_map(static fn(Chat $chat): int => $chat->id, $chats));
        });
    }
}
