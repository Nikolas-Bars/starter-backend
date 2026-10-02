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
            ->assertJsonMissingPath('data.items.0.password');
    }

    public function testSearchesByNameAndEmail(): void
    {
        $this->actingAsUser();
        User::factory()->create(['name' => 'Иван Петров', 'email' => 'ivan@example.com']);
        User::factory()->create(['name' => 'Мария', 'email' => 'maria@example.com']);

        $this->getJson('/api/users?search=Петров')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.email', 'ivan@example.com');

        $this->getJson('/api/users?search=maria@')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Мария');
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
