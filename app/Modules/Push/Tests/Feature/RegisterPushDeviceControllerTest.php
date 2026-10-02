<?php

declare(strict_types=1);

namespace App\Modules\Push\Tests\Feature;

use App\Modules\Push\Models\PushDevice;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class RegisterPushDeviceControllerTest extends TestCase
{
    public function testRegistersDevice(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/push/devices', ['token' => ' fcm-token-1 '])
            ->assertOk()
            ->assertJsonPath('message', 'Уведомления включены.');

        $device = PushDevice::query()->sole();
        self::assertSame($user->id, $device->user_id);
        self::assertSame('fcm-token-1', $device->token);
    }

    public function testRepeatedRegistrationKeepsOneDevice(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/push/devices', ['token' => 'fcm-token-1'])->assertOk();
        $this->putJson('/api/push/devices', ['token' => 'fcm-token-1'])->assertOk();

        self::assertSame(1, PushDevice::query()->count());
    }

    public function testPhoneMovesToUserWhoSignedInOnIt(): void
    {
        $previous = User::factory()->create();
        PushDevice::factory()->create(['user_id' => $previous->id, 'token' => 'shared-phone']);
        $current = $this->actingAsUser();

        $this->putJson('/api/push/devices', ['token' => 'shared-phone'])->assertOk();

        self::assertSame($current->id, PushDevice::query()->sole()->user_id);
    }

    public function testLogoutForgetsDevice(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('android')->plainTextToken;

        $this->withToken($token)->putJson('/api/push/devices', ['token' => 'fcm-token-1'])->assertOk();
        self::assertNotNull(PushDevice::query()->sole()->access_token_id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        self::assertSame(0, PushDevice::query()->count());
    }

    public function testValidatesToken(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/push/devices', ['token' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->putJson('/api/push/devices', ['token' => \str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function testIsNotAvailableForGuests(): void
    {
        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->putJson('/api/push/devices', ['token' => 'fcm-token-1'])->assertForbidden();
    }

    public function testRequiresAuthentication(): void
    {
        $this->putJson('/api/push/devices', ['token' => 'fcm-token-1'])->assertUnauthorized();
    }
}
