<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Feature;

use App\Modules\Call\Actions\AuthenticateWebSocketAction;
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

    public function testRequiresAuthentication(): void
    {
        $this->postJson('/api/calls/ws-ticket')->assertUnauthorized();
    }
}
