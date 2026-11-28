<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Vpn\Models\VpnGrant;
use App\Modules\Vpn\Models\VpnProfile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GetVpnProfilesControllerTest extends TestCase
{
    public function testReturnsEnabledProfilesInOrderToGrantedUser(): void
    {
        $user = $this->actingAsUser();
        VpnGrant::factory()->create(['user_id' => $user->id]);

        $second = VpnProfile::factory()->create(['name' => 'XHTTP', 'position' => 2]);
        $first  = VpnProfile::factory()->create(['name' => 'TCP', 'position' => 1]);
        VpnProfile::factory()->disabled()->create(['position' => 0]);

        $this->getJson('/api/vpn/profiles')
            ->assertOk()
            ->assertJsonPath('message', 'Профили VPN.')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.name', 'TCP')
            ->assertJsonPath('data.0.uri', $first->uri)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function testStoresUriEncrypted(): void
    {
        $profile = VpnProfile::factory()->create();

        $raw = DB::table('vpn_profiles')->where('id', $profile->id)->value('uri');

        self::assertIsString($raw);
        self::assertStringNotContainsString('vless://', $raw);
    }

    public function testIsForbiddenWithoutGrant(): void
    {
        $this->actingAsUser();
        VpnProfile::factory()->create();

        $this->getJson('/api/vpn/profiles')
            ->assertForbidden()
            ->assertJsonPath('message', 'VPN вам недоступен.');
    }

    public function testIsNotAvailableForGuests(): void
    {
        $host  = User::factory()->create();
        $guest = $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));
        VpnGrant::factory()->create(['user_id' => $guest->id]);

        $this->getJson('/api/vpn/profiles')->assertForbidden();
    }

    public function testRequiresAuthentication(): void
    {
        $this->getJson('/api/vpn/profiles')->assertUnauthorized();
    }
}
