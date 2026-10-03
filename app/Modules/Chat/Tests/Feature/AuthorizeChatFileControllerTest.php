<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\ChatAttachment;
use App\Services\FileUrlSigner;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class AuthorizeChatFileControllerTest extends TestCase
{
    public function testSignedPhotoIsShownInline(): void
    {
        $photo = ChatAttachment::factory()->image()->create(['original_name' => 'Море.jpg']);

        $this->authorizeUrl($this->signer()->url($photo->path))
            ->assertOk()
            ->assertHeader('X-File-Type', 'image/jpeg')
            ->assertHeader('X-File-Disposition', 'inline; filename=More.jpg; filename*=utf-8\'\'%D0%9C%D0%BE%D1%80%D0%B5.jpg');

        $this->authorizeUrl($this->signer()->url((string)$photo->thumb_path))
            ->assertOk()
            ->assertHeader('X-File-Type', 'image/jpeg')
            ->assertHeader('X-File-Disposition', 'inline; filename=preview.jpg');
    }

    public function testDocumentIsOnlyDownloaded(): void
    {
        $page = ChatAttachment::factory()->create(['original_name' => 'page.html', 'mime' => 'text/html']);

        $this->authorizeUrl($this->signer()->url($page->path))
            ->assertOk()
            ->assertHeader('X-File-Type', 'application/octet-stream')
            ->assertHeader('X-File-Disposition', 'attachment; filename=page.html');
    }

    public function testRejectsWrongExpiredOrUnfinishedLinks(): void
    {
        $file    = ChatAttachment::factory()->create();
        $other   = ChatAttachment::factory()->create();
        $pending = ChatAttachment::factory()->image()->processing()->create();
        $url     = $this->signer()->url($file->path);

        $this->authorizeUrl(\str_replace($file->path, $other->path, $url))->assertForbidden();
        $this->authorizeUrl((string)\preg_replace('/signature=\w+/', 'signature=' . \str_repeat('0', 64), $url))->assertForbidden();
        $this->authorizeUrl(\strtok($url, '?') . '?expires=1&signature=x')->assertForbidden();
        $this->authorizeUrl($this->signer()->url($pending->path))->assertForbidden();
        $this->authorizeUrl('/api/files/../.env')->assertForbidden();
        $this->getJson('/api/attachments/authorize')->assertForbidden();

        Date::setTestNow(Date::now()->addDays(3));
        $this->authorizeUrl($url)
            ->assertForbidden()
            ->assertJsonPath('message', 'Ссылка на файл устарела или неверна.');
    }

    public function testLinkStaysTheSameDuringTheDay(): void
    {
        $file = ChatAttachment::factory()->create();

        Date::setTestNow('2026-10-03 00:05:00');
        $morning = $this->signer()->url($file->path);
        Date::setTestNow('2026-10-03 23:55:00');

        self::assertSame($morning, $this->signer()->url($file->path));

        // Ссылка, выданная вечером, живёт ещё сутки с лишним
        Date::setTestNow('2026-10-04 23:55:00');
        $this->authorizeUrl($morning)->assertOk();
    }

    private function authorizeUrl(string $url): TestResponse
    {
        return $this->getJson('/api/attachments/authorize', ['X-Forwarded-Uri' => $url]);
    }

    private function signer(): FileUrlSigner
    {
        return $this->app->make(FileUrlSigner::class);
    }
}
