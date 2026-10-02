<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Tests\Feature;

use App\Modules\CallLink\Models\CallLink;
use App\Modules\User\Models\User;
use Tests\TestCase;

final class ShowCallLinkControllerTest extends TestCase
{
    public function testShowsOwnerWithoutEmailToAnyone(): void
    {
        $owner = User::factory()->create(['name' => 'Николай Прасолов']);
        $link  = CallLink::factory()->create(['user_id' => $owner->id]);

        $this->getJson('/api/call-links/' . $link->code)
            ->assertOk()
            ->assertJsonPath('message', 'Ссылка для звонка.')
            ->assertJsonPath('data.code', $link->code)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.owner.name', 'Николай Прасолов')
            ->assertJsonMissingPath('data.owner.email');
    }

    public function testUnknownCodeIsNotFound(): void
    {
        $this->getJson('/api/call-links/unknown')
            ->assertNotFound()
            ->assertJsonPath('message', 'Ссылка для звонка недействительна. Попросите прислать новую.');
    }
}
