<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tests\Unit;

use App\Modules\User\Models\User;
use App\Modules\Vpn\Models\VpnGrant;
use App\Modules\Vpn\Models\VpnProfile;
use Tests\TestCase;

final class VpnCommandsTest extends TestCase
{
    private const string URI = 'vless://6f1c2b0e-8a3d-4c1b-9e7a-2d5f4b3c1a90@185.17.1.109:2083?type=tcp&security=reality&pbk=KEY&sid=ab&sni=www.samsung.com&flow=xtls-rprx-vision';

    public function testAddsProfileWithHiddenUri(): void
    {
        $this->artisan('vpn:profile:add', ['name' => 'Основной · TCP', '--position' => 3])
            ->expectsQuestion('Ссылка или JSON', self::URI)
            ->assertSuccessful();

        $profile = VpnProfile::query()->sole();
        self::assertSame('Основной · TCP', $profile->name);
        self::assertSame(self::URI, $profile->uri);
        self::assertSame(3, $profile->position);
        self::assertTrue($profile->is_enabled);
    }

    public function testRejectsBadNameAndPosition(): void
    {
        $this->artisan('vpn:profile:add', ['name' => \str_repeat('я', 65)])->assertFailed();
        $this->artisan('vpn:profile:add', ['name' => 'X', '--position' => '-1'])->assertFailed();
        $this->artisan('vpn:profile:add', ['name' => 'X', '--position' => 'abc'])->assertFailed();
        $this->artisan('vpn:profile:edit', ['id' => 1, '--stdin' => true])->assertFailed();

        self::assertSame(0, VpnProfile::query()->count());
    }

    public function testRejectsBadUri(): void
    {
        $this->artisan('vpn:profile:add', ['name' => 'X'])
            ->expectsQuestion('Ссылка или JSON', 'https://example.com')
            ->assertFailed();

        self::assertSame(0, VpnProfile::query()->count());
    }

    public function testEditsAndRemovesProfile(): void
    {
        $profile = VpnProfile::factory()->create();

        $this->artisan('vpn:profile:edit', ['id' => $profile->id, '--name' => 'Запасной', '--disable' => true])->assertSuccessful();
        $profile->refresh();
        self::assertSame('Запасной', $profile->name);
        self::assertFalse($profile->is_enabled);

        $this->artisan('vpn:profile:edit', ['id' => $profile->id])->assertFailed();
        $this->artisan('vpn:profile:remove', ['id' => $profile->id])->assertSuccessful();
        self::assertSame(0, VpnProfile::query()->count());
        $this->artisan('vpn:profile:remove', ['id' => $profile->id])->assertFailed();
    }

    public function testGrantsAndRevokesAccessByEmailOrId(): void
    {
        $user = User::factory()->create(['email' => 'mama@example.com']);

        $this->artisan('vpn:access', ['user' => 'mama@example.com'])->assertSuccessful();
        $this->artisan('vpn:access', ['user' => (string)$user->id])->assertSuccessful();
        self::assertSame(1, VpnGrant::query()->where('user_id', $user->id)->count());

        $this->artisan('vpn:access')->expectsOutputToContain('mama@example.com')->assertSuccessful();

        $this->artisan('vpn:access', ['user' => 'mama@example.com'])
            ->expectsOutputToContain('VPN уже был выдан')
            ->assertSuccessful();

        $this->artisan('vpn:access', ['user' => 'mama@example.com', '--revoke' => true])
            ->expectsOutputToContain('VPN забран')
            ->assertSuccessful();
        self::assertSame(0, VpnGrant::query()->count());

        $this->artisan('vpn:access', ['user' => 'mama@example.com', '--revoke' => true])
            ->expectsOutputToContain('VPN и так не был выдан')
            ->assertSuccessful();

        $this->artisan('vpn:access', ['user' => 'nobody@example.com'])->assertFailed();
    }

    public function testDoesNotGrantGuests(): void
    {
        $host  = User::factory()->create();
        $guest = User::factory()->create(['guest_of_id' => $host->id]);

        $this->artisan('vpn:access', ['user' => (string)$guest->id])->assertFailed();
        self::assertSame(0, VpnGrant::query()->count());
    }
}
