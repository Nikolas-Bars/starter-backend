<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

final class ListUsersControllerTest extends TestCase
{
    public function testListsOtherUsersAlphabetically(): void
    {
        $this->actingAsUser(User::factory()->create(['name' => 'Анна']));
        User::factory()->create(['name' => 'Мария Смирнова']);
        User::factory()->create(['name' => 'Иван Петров']);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('message', 'Список пользователей.')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Иван Петров')
            ->assertJsonPath('data.items.1.name', 'Мария Смирнова')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.email', null)
            ->assertJsonMissingPath('data.items.0.password');
    }

    public function testSearchesByNameAndExactEmail(): void
    {
        $this->actingAsUser();
        User::factory()->create(['name' => 'Иван Петров', 'email' => 'ivan@example.com']);
        User::factory()->create(['name' => 'Мария', 'email' => 'maria@example.com']);

        $this->getJson('/api/users?' . \http_build_query(['search' => 'Петров']))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Иван Петров');

        $this->getJson('/api/users?' . \http_build_query(['search' => 'maria@example.com']))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Мария');

        $this->getJson('/api/users?' . \http_build_query(['search' => 'maria@']))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function testSearchesByUsernameWithOrWithoutAt(): void
    {
        $this->actingAsUser();
        User::factory()->create(['name' => 'Иван Петров', 'username' => 'vanya']);
        User::factory()->create(['name' => 'Мария', 'username' => 'masha']);

        foreach (['vanya', '@vanya'] as $search) {
            $this->getJson('/api/users?' . \http_build_query(['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.username', 'vanya');
        }
    }

    public function testHidesGuestsAndIsClosedForThem(): void
    {
        $host = $this->actingAsUser();
        User::factory()->create(['guest_of_id' => $host->id]);

        $this->getJson('/api/users')->assertOk()->assertJsonCount(0, 'data.items');

        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->getJson('/api/users')->assertForbidden();
    }

    public function testValidatesSearchLength(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/users?search=' . \str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function testRequiresAuthentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }
}
