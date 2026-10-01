<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Database\Factories\UserFactory;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    private const URL = '/api/auth/login';

    public function testLogsInWithValidCredentials(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->postJson(self::URL, [
            'email'    => ' Admin@Example.com ',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Вход выполнен.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        self::assertSame(1, $user->tokens()->count());
    }

    public function testRejectsWrongPassword(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->postJson(self::URL, [
            'email'    => 'admin@example.com',
            'password' => 'WrongPassword1',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Неверный email или пароль.');
    }

    public function testRejectsUnknownEmailWithSameMessage(): void
    {
        $this->postJson(self::URL, [
            'email'    => 'nobody@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Неверный email или пароль.');
    }

    public function testThrottlesBruteForce(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->postJson(self::URL, ['email' => 'admin@example.com', 'password' => 'WrongPassword1'])
                ->assertUnauthorized();
        }

        $this->postJson(self::URL, ['email' => 'admin@example.com', 'password' => UserFactory::DEFAULT_PASSWORD])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Слишком много попыток. Повторите через минуту.');
    }

    public function testRespondsInEnglishWhenRequested(): void
    {
        $this->withHeader('X-Locale', 'en')
            ->postJson(self::URL, ['email' => 'nobody@example.com', 'password' => 'Password123'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function testRequiresFields(): void
    {
        $this->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
