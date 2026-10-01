<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

final class LogoutControllerTest extends TestCase
{
    public function testRevokesOnlyCurrentToken(): void
    {
        $user         = User::factory()->create();
        $currentToken = $user->createToken('web')->plainTextToken;
        $otherToken   = $user->createToken('mobile')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Вы вышли из системы.');

        self::assertSame(1, $user->tokens()->count());

        // Guard кэширует пользователя в рамках приложения — сбрасываем между запросами
        $this->app['auth']->forgetGuards();

        $this->withToken($currentToken)->getJson('/api/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->getJson('/api/auth/me')->assertOk();
    }

    public function testRequiresAuthentication(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Необходимо авторизоваться.');
    }
}
