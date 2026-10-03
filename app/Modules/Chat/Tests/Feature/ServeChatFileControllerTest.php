<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Models\ChatAttachment;
use App\Services\FileUrlSigner;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ServeChatFileControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function testServesFileBySignedLink(): void
    {
        $file = ChatAttachment::factory()->create(['original_name' => 'notes.txt', 'mime' => 'text/plain']);
        Storage::disk('attachments')->put($file->path, 'Список покупок');

        $response = $this->get($this->app->make(FileUrlSigner::class)->url($file->path))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment; filename=notes.txt')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        self::assertSame('Список покупок', $response->streamedContent());
    }

    public function testSupportsRangeRequests(): void
    {
        $video = ChatAttachment::factory()->create(['kind' => 'video', 'mime' => 'video/mp4', 'path' => '2026/10/clip.mp4']);
        Storage::disk('attachments')->put($video->path, '0123456789');

        $response = $this->get($this->app->make(FileUrlSigner::class)->url($video->path), ['Range' => 'bytes=2-5'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4');

        self::assertSame('2345', $response->streamedContent());
    }

    public function testRejectsUnsignedLink(): void
    {
        $file = ChatAttachment::factory()->create();
        Storage::disk('attachments')->put($file->path, 'x');

        $this->getJson('/api/files/' . $file->path)->assertForbidden();
    }
}
