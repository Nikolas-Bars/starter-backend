<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Тексты в тестах проверяются на локали по умолчанию
        $this->withHeader('Accept-Language', 'ru');
    }

    /**
     * Авторизует запросы теста как пользователь (без реального токена в БД).
     */
    protected function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
