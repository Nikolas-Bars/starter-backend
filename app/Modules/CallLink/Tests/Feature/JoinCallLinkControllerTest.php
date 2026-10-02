<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tests\Feature;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class JoinCallLinkControllerTest extends TestCase
{
    public function testCreatesGuestOfOwnerWithShortLivedToken(): void
    {
        Date::setTestNow('2026-10-02 10:00:00');
        $owner = User::factory()->create();
        $link  = CallLink::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => '  Аркадий  '])
            ->assertCreated()
            ->assertJsonPath('message', 'Можно звонить.')
            ->assertJsonPath('data.user.name', 'Аркадий')
            ->assertJsonPath('data.user.is_guest', true)
            ->assertJsonPath('data.expires_at', Date::now()->addHours(12)->toIso8601String());

        $guest = User::query()->findOrFail($response->json('data.user.id'));
        self::assertSame($owner->id, $guest->guest_of_id);

        $token = $response->json('data.access_token');
        self::assertIsString($token);

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.is_guest', true);
    }

    public function testGuestCannotListUsers(): void
    {
        $link  = CallLink::factory()->create();
        $token = $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => 'Гость'])->json('data.access_token');
        self::assertIsString($token);

        $this->withToken($token)->getJson('/api/users')->assertForbidden();
    }

    public function testValidatesName(): void
    {
        $link = CallLink::factory()->create();

        $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => \str_repeat('а', 61)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function testUnknownCodeIsNotFound(): void
    {
        $this->postJson('/api/call-links/unknown/join', ['name' => 'Гость'])->assertNotFound();

        self::assertSame(0, User::query()->count());
    }

    public function testLimitsAttemptsPerIp(): void
    {
        $link = CallLink::factory()->create();

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => 'Гость'])->assertCreated();
        }

        $this->postJson('/api/call-links/' . $link->code . '/join', ['name' => 'Гость'])->assertTooManyRequests();
    }
}
