<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tests\Feature;

use App\Modules\Chat\Jobs\ProcessChatAttachmentJob;
use App\Modules\Chat\Models\ChatAttachment;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

final class UploadChatAttachmentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Config::set('services.telegram', ['bot_token' => 'test-token', 'chat_id' => '42']);
    }

    public function testPhotoIsStoredAndQueuedForCompression(): void
    {
        $me = $this->actingAsUser();

        $response = $this->post('/api/attachments', ['file' => $this->file('IMG_2041.jpg', $this->jpeg())])
            ->assertCreated()
            ->assertJsonPath('message', 'Файл загружен.')
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.name', 'IMG_2041.jpg')
            ->assertJsonPath('data.url', null);

        $attachment = ChatAttachment::query()->findOrFail($response->json('data.id'));
        self::assertSame($me->id, $attachment->user_id);
        self::assertNull($attachment->message_id);
        self::assertStringEndsWith('.src', $attachment->path);
        Storage::disk('attachments')->assertExists($attachment->path);

        Queue::assertPushedOn('media', ProcessChatAttachmentJob::class, static fn(ProcessChatAttachmentJob $job): bool => $job->attachmentId === $attachment->id);
    }

    public function testDocumentIsReadyAtOnce(): void
    {
        $this->actingAsUser();

        $this->post('/api/attachments', ['file' => $this->file('договор.txt', 'Текст договора')])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'file')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.mime', 'text/plain')
            ->assertJsonPath('data.size', \strlen('Текст договора'));

        Queue::assertNothingPushed();
    }

    public function testTypeComesFromContentNotFromName(): void
    {
        $this->actingAsUser();

        // SVG может содержать скрипт: только как файл на скачивание
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->post('/api/attachments', ['file' => $this->file('photo.jpg', $svg)])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'file');

        $this->post('/api/attachments', ['file' => $this->file('photo.jpg', $this->jpeg()), 'as_file' => true])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'file');

        $this->post('/api/attachments', ['file' => $this->file('voice.jpg', $this->jpeg()), 'voice' => true])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'image');

        Queue::assertPushed(ProcessChatAttachmentJob::class, 1);
    }

    public function testNameIsCleaned(): void
    {
        $this->actingAsUser();

        $this->post('/api/attachments', ['file' => $this->file("../../etc/pass\x07wd.txt", 'x')])
            ->assertCreated()
            ->assertJsonPath('data.name', 'passwd.txt');
    }

    public function testValidatesFileAndSize(): void
    {
        $this->actingAsUser();
        Config::set('attachments.max_file_bytes', 1024 * 1024);

        $this->postJson('/api/attachments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->post('/api/attachments', ['file' => $this->file('big.bin', \str_repeat('x', 2 * 1024 * 1024))], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        self::assertSame(0, ChatAttachment::query()->count());
    }

    public function testFullStorageRejectsUploadAndWarnsAdminOnce(): void
    {
        $me = $this->actingAsUser();
        Config::set('attachments.quota_bytes', 1000);
        ChatAttachment::factory()->create(['user_id' => $me->id, 'size' => 950]);

        $this->post('/api/attachments', ['file' => $this->file('a.txt', \str_repeat('x', 100))], ['Accept' => 'application/json'])
            ->assertStatus(507)
            ->assertJsonPath('message', 'Место для файлов закончилось. Попробуйте позже.');

        $this->post('/api/attachments', ['file' => $this->file('a.txt', \str_repeat('x', 100))], ['Accept' => 'application/json'])
            ->assertStatus(507);

        self::assertSame(1, ChatAttachment::query()->count());
        Http::assertSentCount(1);
        Http::assertSent(static fn(Request $request): bool => \str_contains($request->url(), '/bottest-token/sendMessage')
            && $request['chat_id'] === '42'
            && \str_contains((string)$request['text'], '95%'));
    }

    public function testWarnsAgainOnlyAfterSpaceWasFreed(): void
    {
        $me = $this->actingAsUser();
        Config::set('attachments.quota_bytes', 1000);
        $big = ChatAttachment::factory()->create(['user_id' => $me->id, 'size' => 890]);

        // 89% + 10 байт = 90%: предупреждение
        $this->post('/api/attachments', ['file' => $this->file('a.txt', \str_repeat('x', 10))])->assertCreated();
        $this->post('/api/attachments', ['file' => $this->file('b.txt', 'x')])->assertCreated();
        Http::assertSentCount(1);

        // Место освободили — следующее заполнение снова предупредит
        $big->delete();
        $this->post('/api/attachments', ['file' => $this->file('c.txt', 'x')])->assertCreated();
        ChatAttachment::factory()->create(['user_id' => $me->id, 'size' => 900]);
        $this->post('/api/attachments', ['file' => $this->file('d.txt', 'x')])->assertCreated();
        Http::assertSentCount(2);
    }

    public function testGuestCannotUpload(): void
    {
        $this->post('/api/attachments', ['file' => $this->file('a.jpg', $this->jpeg())], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    /**
     * Настоящий загруженный файл: тип определяется по содержимому, а не по имени, как у фейков Laravel
     */
    private function file(string $name, string $content): UploadedFile
    {
        $path = (string)\tempnam(\sys_get_temp_dir(), 'upload');
        \file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function jpeg(): string
    {
        $image = new Imagick();
        $image->newImage(64, 32, new ImagickPixel('red'), 'jpeg');

        return $image->getImageBlob();
    }
}
