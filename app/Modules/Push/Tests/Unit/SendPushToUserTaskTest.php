<?php

declare(strict_types=1);

namespace App\Modules\Push\Tests\Unit;

use App\Modules\Push\Models\PushDevice;
use App\Modules\Push\Tasks\SendPushToUserTask;
use App\Modules\User\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\FakeFirebase;
use Tests\TestCase;

final class SendPushToUserTaskTest extends TestCase
{
    public function testSendsDataMessageToEveryDeviceOfUser(): void
    {
        FakeFirebase::enable();
        $user = User::factory()->create();
        PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'phone']);
        PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tablet']);
        PushDevice::factory()->create(['token' => 'someone-else']);

        $sent = $this->app->make(SendPushToUserTask::class)->run($user->id, ['type' => 'call.incoming'], 30);

        self::assertSame(2, $sent);

        Http::assertSent(static fn(Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && \substr_count((string)$request['assertion'], '.') === 2);

        Http::assertSent(static fn(Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/' . FakeFirebase::PROJECT . '/messages:send'
            && $request->hasHeader('Authorization', 'Bearer ' . FakeFirebase::ACCESS_TOKEN)
            && $request['message']['token'] === 'phone'
            && $request['message']['data'] === ['type' => 'call.incoming']
            && $request['message']['android'] === ['priority' => 'HIGH', 'ttl' => '30s']);

        Http::assertNotSent(static fn(Request $request): bool => ($request['message']['token'] ?? null) === 'someone-else');
    }

    public function testForgetsUninstalledDevicesOnly(): void
    {
        FakeFirebase::enable(['uninstalled' => 404, 'flaky' => 500]);
        $user = User::factory()->create();

        foreach (['uninstalled', 'flaky', 'fine'] as $token) {
            PushDevice::factory()->create(['user_id' => $user->id, 'token' => $token]);
        }

        $sent = $this->app->make(SendPushToUserTask::class)->run($user->id, ['type' => 'call.ended'], 60);

        self::assertSame(1, $sent);
        self::assertEqualsCanonicalizing(['flaky', 'fine'], PushDevice::query()->pluck('token')->all());
    }

    public function testDoesNothingWithoutFirebaseKey(): void
    {
        Http::fake();
        $user = User::factory()->create();
        PushDevice::factory()->create(['user_id' => $user->id]);

        self::assertSame(0, $this->app->make(SendPushToUserTask::class)->run($user->id, ['type' => 'call.ended'], 60));

        Http::assertNothingSent();
    }
}
