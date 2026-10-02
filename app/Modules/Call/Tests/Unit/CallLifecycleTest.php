<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Unit;

use App\Modules\Call\Actions\AcceptCallAction;
use App\Modules\Call\Actions\EndCallAction;
use App\Modules\Call\Actions\FinishStaleCallsAction;
use App\Modules\Call\Actions\InitiateCallAction;
use App\Modules\Call\Actions\MarkMissedCallsAction;
use App\Modules\Call\Actions\RejectCallAction;
use App\Modules\Call\Enums\CallStatusEnum;
use App\Modules\Call\Exceptions\CallNotFoundException;
use App\Modules\Call\Exceptions\InvalidCalleeException;
use App\Modules\Call\Exceptions\InvalidCallStateException;
use App\Modules\Call\Exceptions\NotCallParticipantException;
use App\Modules\Call\Exceptions\UserBusyException;
use App\Modules\Call\Models\Call;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class CallLifecycleTest extends TestCase
{
    private User $caller;

    private User $callee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caller = User::factory()->create();
        $this->callee = User::factory()->create();
    }

    public function testInitiateCreatesRingingCallForOnlineCallee(): void
    {
        $call = $this->initiate(calleeOnline: true);

        self::assertSame(CallStatusEnum::Ringing, $call->status);
        self::assertSame($this->caller->id, $call->caller->id);
        self::assertSame($this->callee->id, $call->callee->id);
        self::assertNull($call->ended_at);
    }

    public function testInitiateFinishesCallImmediatelyWhenCalleeOffline(): void
    {
        $call = $this->initiate(calleeOnline: false);

        self::assertSame(CallStatusEnum::Unavailable, $call->status);
        self::assertNotNull($call->ended_at);
    }

    public function testInitiateMarksCallBusyWhenCalleeAlreadyTalking(): void
    {
        Call::factory()->between(User::factory()->create(), $this->callee)->active()->create();

        self::assertSame(CallStatusEnum::Busy, $this->initiate()->status);
    }

    public function testInitiateRejectsCallerWhoIsAlreadyInCall(): void
    {
        Call::factory()->between($this->caller, User::factory()->create())->active()->create();

        $this->expectException(UserBusyException::class);
        $this->initiate();
    }

    public function testInitiateRejectsCallingYourself(): void
    {
        $this->expectException(InvalidCalleeException::class);
        $this->app->make(InitiateCallAction::class)->run($this->caller, $this->caller->id, true);
    }

    public function testInitiateRejectsUnknownCallee(): void
    {
        $this->expectException(InvalidCalleeException::class);
        $this->app->make(InitiateCallAction::class)->run($this->caller, 999_999, true);
    }

    public function testCalleeAcceptsRingingCall(): void
    {
        $call = $this->app->make(AcceptCallAction::class)->run($this->callee, $this->initiate()->id);

        self::assertSame(CallStatusEnum::Active, $call->status);
        self::assertNotNull($call->answered_at);
    }

    public function testCallerCannotAcceptOwnCall(): void
    {
        $this->expectException(NotCallParticipantException::class);
        $this->app->make(AcceptCallAction::class)->run($this->caller, $this->initiate()->id);
    }

    public function testStrangerCannotAcceptCall(): void
    {
        $this->expectException(NotCallParticipantException::class);
        $this->app->make(AcceptCallAction::class)->run(User::factory()->create(), $this->initiate()->id);
    }

    public function testCannotAcceptCallTwice(): void
    {
        $call = $this->initiate();
        $this->app->make(AcceptCallAction::class)->run($this->callee, $call->id);

        $this->expectException(InvalidCallStateException::class);
        $this->app->make(AcceptCallAction::class)->run($this->callee, $call->id);
    }

    public function testCannotAcceptMissingCall(): void
    {
        $this->expectException(CallNotFoundException::class);
        $this->app->make(AcceptCallAction::class)->run($this->callee, 999_999);
    }

    public function testCalleeRejectsRingingCall(): void
    {
        $call = $this->app->make(RejectCallAction::class)->run($this->callee, $this->initiate()->id);

        self::assertSame(CallStatusEnum::Rejected, $call->status);
        self::assertNotNull($call->ended_at);
        self::assertNull($call->durationSeconds());
    }

    public function testCallerHangingUpBeforeAnswerMakesCallMissed(): void
    {
        $call = $this->app->make(EndCallAction::class)->run($this->caller, $this->initiate()->id);

        self::assertSame(CallStatusEnum::Missed, $call->status);
    }

    public function testCalleeHangingUpBeforeAnswerRejectsCall(): void
    {
        $call = $this->app->make(EndCallAction::class)->run($this->callee, $this->initiate()->id);

        self::assertSame(CallStatusEnum::Rejected, $call->status);
    }

    public function testHangingUpActiveCallEndsItWithDuration(): void
    {
        $call = Call::factory()->between($this->caller, $this->callee)->active()->create([
            'answered_at' => Date::now()->subSeconds(42),
        ]);

        $ended = $this->app->make(EndCallAction::class)->run($this->callee, $call->id);

        self::assertSame(CallStatusEnum::Ended, $ended->status);
        self::assertSame(42, $ended->durationSeconds());
    }

    public function testCannotHangUpFinishedCall(): void
    {
        $call = Call::factory()->between($this->caller, $this->callee)->ended()->create();

        $this->expectException(InvalidCallStateException::class);
        $this->app->make(EndCallAction::class)->run($this->caller, $call->id);
    }

    public function testUnansweredCallsBecomeMissedAfterTimeout(): void
    {
        $expired = Call::factory()->between($this->caller, $this->callee)->create([
            'started_at' => Date::now()->subSeconds(31),
        ]);
        $fresh = Call::factory()->create(['started_at' => Date::now()->subSeconds(5)]);

        $missed = $this->app->make(MarkMissedCallsAction::class)->run(30);

        self::assertSame([$expired->id], $missed->modelKeys());
        self::assertSame(CallStatusEnum::Missed, $missed->firstOrFail()->status);
        self::assertSame(CallStatusEnum::Missed, $expired->refresh()->status);
        self::assertSame(CallStatusEnum::Ringing, $fresh->refresh()->status);
    }

    public function testFinishStaleCallsClosesEverythingOngoing(): void
    {
        $ringing  = Call::factory()->create();
        $active   = Call::factory()->active()->create();
        $finished = Call::factory()->ended()->create();

        self::assertSame(2, $this->app->make(FinishStaleCallsAction::class)->run());
        self::assertSame(CallStatusEnum::Missed, $ringing->refresh()->status);
        self::assertSame(CallStatusEnum::Ended, $active->refresh()->status);
        self::assertSame(CallStatusEnum::Ended, $finished->refresh()->status);
    }

    private function initiate(bool $calleeOnline = true): Call
    {
        return $this->app->make(InitiateCallAction::class)->run($this->caller, $this->callee->id, $calleeOnline);
    }
}
