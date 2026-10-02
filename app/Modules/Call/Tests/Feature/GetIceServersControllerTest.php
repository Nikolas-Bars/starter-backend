<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

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
