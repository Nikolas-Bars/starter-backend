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

    public function testDoesNotSearchByNameOrEmail(): void
    {
        $this->actingAsUser();
        User::factory()->create(['name' => 'Иван Петров', 'email' => 'ivan@example.com', 'username' => 'vanya']);

        foreach (['Петров', 'ivan@example.com'] as $search) {
            $this->getJson('/api/users?' . \http_build_query(['search' => $search]))
                ->assertOk()
                ->assertJsonCount(0, 'data.items');
        }
    }

    public function testSearchesByPartOfUsernameWithOrWithoutAt(): void
    {
        $this->actingAsUser();
        User::factory()->create(['name' => 'Иван Петров', 'username' => 'vanya']);
        User::factory()->create(['name' => 'Мария', 'username' => 'masha']);
        User::factory()->create(['name' => 'Без ника', 'username' => null]);

        foreach (['vanya', '@vanya', 'VAN', 'any'] as $search) {
            $this->getJson('/api/users?' . \http_build_query(['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.username', 'vanya');
        }
    }

    public function testTreatsLikeWildcardsLiterally(): void
    {
        $this->actingAsUser();
        User::factory()->create(['username' => 'vanya']);
        User::factory()->create(['username' => 'ma_sha']);

        $this->getJson('/api/users?' . \http_build_query(['search' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->getJson('/api/users?' . \http_build_query(['search' => '_']))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.username', 'ma_sha');
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
