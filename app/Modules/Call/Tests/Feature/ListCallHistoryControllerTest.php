<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

use App\Modules\Call\Models\Call;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class ListCallHistoryControllerTest extends TestCase
{
    public function testReturnsOnlyCallsOfCurrentUserNewestFirst(): void
    {
        $user  = $this->actingAsUser();
        $other = User::factory()->create();

        $outgoing = Call::factory()->between($user, $other)->ended(95)->create(['started_at' => Date::now()->subHour()]);
        $incoming = Call::factory()->between($other, $user)->create(['started_at' => Date::now()]);
        Call::factory()->create();

        $this->getJson('/api/calls')
            ->assertOk()
            ->assertJsonPath('message', 'История звонков.')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $incoming->id)
            ->assertJsonPath('data.items.0.caller.id', $other->id)
            ->assertJsonPath('data.items.0.status', 'ringing')
            ->assertJsonPath('data.items.1.id', $outgoing->id)
            ->assertJsonPath('data.items.1.callee.id', $other->id)
            ->assertJsonPath('data.items.1.duration_seconds', 95)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonMissingPath('data.items.0.caller.password');
    }

    public function testPaginatesHistory(): void
    {
        $user = $this->actingAsUser();
        Call::factory()->count(21)->between($user, User::factory()->create())->ended()->create();

        $this->getJson('/api/calls?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.per_page', 20);
    }

    public function testRequiresAuthentication(): void
    {
        $this->getJson('/api/calls')->assertUnauthorized();
    }
}
