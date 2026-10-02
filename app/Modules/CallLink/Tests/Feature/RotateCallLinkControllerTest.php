<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tests\Feature;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class RotateCallLinkControllerTest extends TestCase
{
    public function testOldLinkStopsWorking(): void
    {
        $user = $this->actingAsUser();
        $old  = CallLink::factory()->create(['user_id' => $user->id]);

        $code = $this->postJson('/api/call-link/rotate')
            ->assertOk()
            ->assertJsonPath('message', 'Ссылка перевыпущена: старая больше не работает.')
            ->json('data.code');

        self::assertIsString($code);
        self::assertNotSame($old->code, $code);

        $this->getJson('/api/call-links/' . $old->code)->assertNotFound();
        $this->getJson('/api/call-links/' . $code)->assertOk();
    }

    public function testCreatesLinkIfThereWasNone(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/call-link/rotate')->assertOk();

        self::assertSame(1, CallLink::query()->where('user_id', $user->id)->count());
    }

    public function testIsNotAvailableForGuests(): void
    {
        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->postJson('/api/call-link/rotate')->assertForbidden();
    }

    public function testRequiresAuthentication(): void
    {
        $this->postJson('/api/call-link/rotate')->assertUnauthorized();
    }
}
