<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatAttachment;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PruneAttachmentsCommandTest extends TestCase
{
    public function testRemovesFilesNobodySentAndOldOnesOnRequest(): void
    {
        $disk    = Storage::fake('attachments');
        $me      = User::factory()->create();
        $message = ChatMessage::factory()->inChat(Chat::factory()->between($me, User::factory()->create())->create(), $me)->create();

        Date::setTestNow('2026-09-01 12:00:00');
        $oldSent = ChatAttachment::factory()->image()->create(['user_id' => $me->id, 'message_id' => $message->id, 'size' => 300]);
        Date::setTestNow('2026-10-02 10:00:00');
        $orphan = ChatAttachment::factory()->create(['user_id' => $me->id, 'size' => 200]);
        Date::setTestNow('2026-10-03 09:00:00');
        $fresh = ChatAttachment::factory()->create(['user_id' => $me->id]);
        $sent  = ChatAttachment::factory()->create(['user_id' => $me->id, 'message_id' => $message->id]);
        Date::setTestNow('2026-10-03 12:00:00');

        foreach ([$oldSent->path, (string)$oldSent->thumb_path, $orphan->path, $fresh->path, $sent->path] as $path) {
            $disk->put($path, 'x');
        }

        $this->artisan('attachments:prune')
            ->expectsOutputToContain('Удалено файлов: 1')
            ->assertSuccessful();

        self::assertNull($orphan->fresh());
        $disk->assertMissing($orphan->path);
        self::assertNotNull($fresh->fresh());
        self::assertNotNull($oldSent->fresh());

        $this->artisan('attachments:prune', ['--before' => '2026-10-01'])
            ->expectsOutputToContain('Удалено файлов: 1, освобождено 300.0 B')
            ->assertSuccessful();

        self::assertNull($oldSent->fresh());
        $disk->assertMissing([$oldSent->path, (string)$oldSent->thumb_path]);
        self::assertNotNull($sent->fresh());
        self::assertNotNull($message->fresh());

        $this->artisan('attachments:prune', ['--before' => 'вчера'])->assertFailed();
    }

    public function testShowsUsage(): void
    {
        ChatAttachment::factory()->create(['size' => 1024 * 1024 * 1024]);

        $this->artisan('attachments:usage')
            ->expectsOutputToContain('Занято 1.0 GB из 10.0 GB (10%)')
            ->assertSuccessful();
    }
}
