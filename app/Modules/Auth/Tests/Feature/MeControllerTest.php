<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Feature;

use Tests\TestCase;

final class MeControllerTest extends TestCase
{
    public function testReturnsCurrentUser(): void
    {
        $user = $this->actingAsUser();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function testRejectsInvalidToken(): void
    {
        $this->withToken('1|not-a-real-token')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function testRejectsRequestWithoutAcceptHeader(): void
    {
        // Без Accept: application/json API всё равно отвечает JSON, а не редиректом на логин
        $this->get('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }
}
