<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class GetIceServersControllerTest extends TestCase
{
    public function testReturnsConfiguredIceServers(): void
    {
        $this->actingAsUser();

        config(['calls.ice_servers' => [
            ['urls' => ['stun:stun.example.com:3478']],
            ['urls' => ['turn:turn.example.com:3478'], 'username' => 'u', 'credential' => 'p'],
        ]]);

        $this->getJson('/api/calls/ice-servers')
            ->assertOk()
            ->assertJsonPath('data.ice_servers.0.urls.0', 'stun:stun.example.com:3478')
            ->assertJsonPath('data.ice_servers.1.username', 'u');
    }

    public function testIssuesTemporaryTurnCredentialsWhenSecretIsSet(): void
    {
        $user = $this->actingAsUser();
        Date::setTestNow('2026-10-02 10:00:00');

        config([
            'calls.ice_servers' => [['urls' => ['stun:stun.example.com:3478']]],
            'calls.turn'        => ['urls' => ['turn:turn.example.com:3478'], 'secret' => 'shared-secret', 'ttl' => 3600],
        ]);

        $username = (Date::now()->getTimestamp() + 3600) . ':' . $user->id;

        $this->getJson('/api/calls/ice-servers')
            ->assertOk()
            ->assertJsonCount(2, 'data.ice_servers')
            ->assertJsonPath('data.ice_servers.1.urls.0', 'turn:turn.example.com:3478')
            ->assertJsonPath('data.ice_servers.1.username', $username)
            ->assertJsonPath('data.ice_servers.1.credential', \base64_encode(\hash_hmac('sha1', $username, 'shared-secret', true)));
    }

    public function testDefaultsToPublicStun(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/calls/ice-servers')
            ->assertOk()
            ->assertJsonPath('data.ice_servers.0.urls.0', 'stun:stun.l.google.com:19302');
    }

    public function testRequiresAuthentication(): void
    {
        $this->getJson('/api/calls/ice-servers')->assertUnauthorized();
    }
}
