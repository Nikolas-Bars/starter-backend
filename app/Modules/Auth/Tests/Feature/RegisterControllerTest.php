<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegisterControllerTest extends TestCase
{
    private const URL = '/api/auth/register';

    public function testRegistersUserAndReturnsToken(): void
    {
        $response = $this->postJson(self::URL, [
            'name'                  => 'Иван Петров',
            'username'              => '@Ivan_Petrov',
            'email'                 => 'Ivan@Example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Регистрация прошла успешно.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'ivan@example.com')
            ->assertJsonPath('data.user.username', 'ivan_petrov')
            ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user' => ['id', 'name', 'email']]]);

        $user = User::query()->where('email', 'ivan@example.com')->firstOrFail();
        self::assertNotSame('Password123', $user->password);
        self::assertTrue(Hash::check('Password123', $user->password));
        self::assertSame(1, $user->tokens()->count());
    }

    public function testIssuedTokenAuthorizesRequests(): void
    {
        $token = $this->postJson(self::URL, [
            'name'                  => 'Иван Петров',
            'username'              => 'ivan2',
            'email'                 => 'ivan@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ])->json('data.access_token');

        $this->withToken((string)$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ivan@example.com');
    }

    public function testRejectsTakenEmail(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(self::URL, [
            'name'                  => 'Дубль',
            'username'              => 'dubl',
            'email'                 => 'TAKEN@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Такое значение поля «email» уже занято.');
    }

    public function testRejectsWeakPassword(): void
    {
        $this->postJson(self::URL, [
            'name'                  => 'Иван',
            'username'              => 'weak',
            'email'                 => 'weak@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function testRejectsPasswordConfirmationMismatch(): void
    {
        $this->postJson(self::URL, [
            'name'                  => 'Иван',
            'username'              => 'mismatch',
            'email'                 => 'mismatch@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password124',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function testRequiresAllFields(): void
    {
        $this->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Проверьте правильность заполнения полей.')
            ->assertJsonValidationErrors(['name', 'username', 'email', 'password']);
    }

    public function testRejectsInvalidUsername(): void
    {
        foreach (['iv', 'иван', 'ivan petrov', \str_repeat('a', 33)] as $username) {
            $this->postJson(self::URL, [
                'name'                  => 'Иван',
                'username'              => $username,
                'email'                 => 'nick@example.com',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.username.0', 'Ник: латинские буквы, цифры и _, от 3 до 32 символов.');
        }
    }

    public function testRejectsTakenUsername(): void
    {
        User::factory()->create(['username' => 'ivan']);

        $this->postJson(self::URL, [
            'name'                  => 'Иван',
            'username'              => 'IVAN',
            'email'                 => 'other@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }
}
