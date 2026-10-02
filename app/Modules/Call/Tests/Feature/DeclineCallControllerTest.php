<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Models\Call;
use App\Modules\Call\Tasks\SignCallDeclineTask;
use App\Modules\Call\WebSockets\MessageRouter;
use App\Services\RealtimeBus;
use Tests\TestCase;

final class DeclineCallControllerTest extends TestCase
{
    public function testDeclinesRingingCallAndTellsSignalingServer(): void
    {
        $call = Call::factory()->create(['status' => CallStatusEnum::Ringing]);

        $this->postJson('/api/calls/' . $call->id . '/decline', ['token' => $this->tokenFor($call)])
            ->assertOk()
            ->assertJsonPath('message', 'Вызов отклонён.');

        self::assertSame(CallStatusEnum::Rejected, $call->refresh()->status);
        self::assertNotNull($call->ended_at);

        $events = $this->app->make(RealtimeBus::class)->drain(10);
        self::assertSame([[
            'user_ids' => [],
            'message'  => ['type' => MessageRouter::CALL_FINISHED_EVENT, 'data' => ['call_id' => $call->id]],
        ]], $events);
    }

    public function testKeyFitsOnlyItsCall(): void
    {
        $call  = Call::factory()->create(['status' => CallStatusEnum::Ringing]);
        $other = Call::factory()->create(['status' => CallStatusEnum::Ringing]);

        $this->postJson('/api/calls/' . $call->id . '/decline', ['token' => $this->tokenFor($other)])
            ->assertNotFound()
            ->assertJsonPath('message', 'Звонок не найден.');

        $this->postJson('/api/calls/999999/decline', ['token' => $this->tokenFor($call)])->assertNotFound();

        self::assertSame(CallStatusEnum::Ringing, $call->refresh()->status);
    }

    public function testCannotDeclineCallThatNoLongerRings(): void
    {
        $call = Call::factory()->create(['status' => CallStatusEnum::Missed]);

        $this->postJson('/api/calls/' . $call->id . '/decline', ['token' => $this->tokenFor($call)])->assertConflict();

        self::assertSame(CallStatusEnum::Missed, $call->refresh()->status);
    }

    public function testRequiresToken(): void
    {
        $call = Call::factory()->create(['status' => CallStatusEnum::Ringing]);

        $this->postJson('/api/calls/' . $call->id . '/decline')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    private function tokenFor(Call $call): string
    {
        return $this->app->make(SignCallDeclineTask::class)->run($call);
    }
}
