<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tests\Feature;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class GetOwnCallLinkControllerTest extends TestCase
{
    public function testCreatesLinkOnFirstRequestAndKeepsIt(): void
    {
        $user = $this->actingAsUser();

        $code = $this->getJson('/api/call-link')
            ->assertOk()
            ->assertJsonPath('message', 'Ваша ссылка для звонка.')
            ->json('data.code');

        self::assertIsString($code);
        self::assertSame(12, \strlen($code));

        $this->getJson('/api/call-link')->assertOk()->assertJsonPath('data.code', $code);
        self::assertSame(1, CallLink::query()->where('user_id', $user->id)->count());
    }

    public function testIsNotAvailableForGuests(): void
    {
        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->getJson('/api/call-link')
            ->assertForbidden()
            ->assertJsonPath('message', 'Гостям это недоступно. Зарегистрируйтесь, чтобы продолжить.');
    }

    public function testRequiresAuthentication(): void
    {
        $this->getJson('/api/call-link')->assertUnauthorized();
    }
}
