<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

use App\Modules\Call\Actions\AuthenticateWebSocketAction;
use App\Modules\Call\Tasks\IssueWebSocketTicketTask;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class IssueWebSocketTicketControllerTest extends TestCase
{
    public function testIssuesOneTimeTicketForCurrentUser(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/calls/ws-ticket')
            ->assertCreated()
            ->assertJsonPath('message', 'Билет на подключение выдан.')
            ->assertJsonPath('data.expires_in', 30);

        $ticket = $response->json('data.ticket');
        self::assertIsString($ticket);

        $authenticate = $this->app->make(AuthenticateWebSocketAction::class);

        self::assertSame($user->id, $authenticate->run($ticket)?->id);
        self::assertNull($authenticate->run($ticket), 'Билет сгорает после первого подключения');
        self::assertNull($authenticate->run('forged'));
        self::assertNull($authenticate->run(''));
    }

    public function testAcceptsUserIdThatCacheReturnsAsString(): void
    {
        $user = $this->actingAsUser();
        Cache::put(IssueWebSocketTicketTask::CACHE_PREFIX . 'from-redis', (string)$user->id, 30);

        self::assertSame($user->id, $this->app->make(AuthenticateWebSocketAction::class)->run('from-redis')?->id);
    }

    public function testRequiresAuthentication(): void
    {
        $this->postJson('/api/calls/ws-ticket')->assertUnauthorized();
    }
}
