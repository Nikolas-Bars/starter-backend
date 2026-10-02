<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

final class UpdateProfileControllerTest extends TestCase
{
    public function testUpdatesNameAndNormalizesUsername(): void
    {
        $user = $this->actingAsUser();

        $this->patchJson('/api/profile', ['name' => '  Иван  ', 'username' => '@Ivan_Petrov'])
            ->assertOk()
            ->assertJsonPath('message', 'Профиль сохранён.')
            ->assertJsonPath('data.name', 'Иван')
            ->assertJsonPath('data.username', 'ivan_petrov');

        self::assertSame('ivan_petrov', $user->refresh()->username);
    }

    public function testRemovesUsername(): void
    {
        $user = $this->actingAsUser(User::factory()->create(['username' => 'old_nick']));

        $this->patchJson('/api/profile', ['name' => $user->name, 'username' => ''])
            ->assertOk()
            ->assertJsonPath('data.username', null);
    }

    public function testKeepsOwnUsernameAndRejectsTakenOne(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = $this->actingAsUser(User::factory()->create(['username' => 'mine']));

        $this->patchJson('/api/profile', ['name' => $user->name, 'username' => 'mine'])->assertOk();

        $this->patchJson('/api/profile', ['name' => $user->name, 'username' => 'TAKEN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function testValidatesUsernameFormat(): void
    {
        $this->actingAsUser();

        foreach (['ab', 'иван', 'with space', \str_repeat('a', 33)] as $username) {
            $this->patchJson('/api/profile', ['name' => 'Иван', 'username' => $username])
                ->assertUnprocessable()
                ->assertJsonPath('errors.username.0', 'Ник: латинские буквы, цифры и _, от 3 до 32 символов.');
        }

        $this->patchJson('/api/profile', ['name' => 'Иван'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function testIsClosedForGuestsAndAnonymous(): void
    {
        $this->patchJson('/api/profile', ['name' => 'Иван', 'username' => null])->assertUnauthorized();

        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->patchJson('/api/profile', ['name' => 'Иван', 'username' => null])->assertForbidden();
    }
}
