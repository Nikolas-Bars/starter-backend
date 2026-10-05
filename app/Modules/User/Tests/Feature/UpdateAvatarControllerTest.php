<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\FileUrlSigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

final class UpdateAvatarControllerTest extends TestCase
{
    private const string URL = '/api/profile/avatar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function testCropsPhotoToSquareJpegWithoutMetadata(): void
    {
        $user = $this->actingAsUser();

        $response = $this->post(self::URL, ['avatar' => $this->photo(1200, 800)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Аватарка обновлена.')
            ->assertJsonPath('data.id', $user->id);

        $path = (string)$user->refresh()->avatar_path;
        self::assertStringStartsWith('avatars/', $path);
        self::assertSame($this->app->make(FileUrlSigner::class)->url($path), $response->json('data.avatar_url'));

        $avatar = new Imagick(Storage::disk('attachments')->path($path));
        self::assertSame('JPEG', $avatar->getImageFormat());
        self::assertSame([512, 512], [$avatar->getImageWidth(), $avatar->getImageHeight()]);
        self::assertSame([], $avatar->getImageProperties('exif:*'));
    }

    public function testReplacingAvatarDeletesPreviousFile(): void
    {
        $user = $this->actingAsUser();

        $this->post(self::URL, ['avatar' => $this->photo(600, 600)], ['Accept' => 'application/json'])->assertOk();
        $first = (string)$user->refresh()->avatar_path;

        $this->post(self::URL, ['avatar' => $this->photo(700, 500)], ['Accept' => 'application/json'])->assertOk();
        $second = (string)$user->refresh()->avatar_path;

        self::assertNotSame($first, $second);
        Storage::disk('attachments')->assertMissing($first);
        Storage::disk('attachments')->assertExists($second);
    }

    public function testServesAvatarBySignedLink(): void
    {
        $user = $this->actingAsUser();
        $this->post(self::URL, ['avatar' => $this->photo(600, 600)], ['Accept' => 'application/json'])->assertOk();

        $this->get($this->app->make(FileUrlSigner::class)->url((string)$user->refresh()->avatar_path))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename=avatar.jpg');
    }

    public function testRejectsNonImageAndUnreadableImage(): void
    {
        $this->actingAsUser();

        $this->post(self::URL, ['avatar' => UploadedFile::fake()->createWithContent('notes.txt', 'текст')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->post(self::URL, ['avatar' => UploadedFile::fake()->createWithContent('broken.jpg', "\xFF\xD8\xFF\xE0broken")], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        self::assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function testClosedForGuestsAndAnonymous(): void
    {
        $this->post(self::URL, ['avatar' => $this->photo(100, 100)], ['Accept' => 'application/json'])->assertUnauthorized();

        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->post(self::URL, ['avatar' => $this->photo(100, 100)], ['Accept' => 'application/json'])->assertForbidden();
    }

    private function photo(int $width, int $height): UploadedFile
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('#3366cc'), 'jpeg');
        $image->setImageProperty('comment', 'secret');
        $path = \tempnam(\sys_get_temp_dir(), 'photo') . '.jpg';
        $image->writeImage($path);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }
}
