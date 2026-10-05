<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

final class DeleteAvatarControllerTest extends TestCase
{
    private const string URL = '/api/profile/avatar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function testDeletesAvatar(): void
    {
        $user = $this->actingAsUser();
        $this->post(self::URL, ['avatar' => $this->photo(600, 600)], ['Accept' => 'application/json'])->assertOk();
        $path = (string)$user->refresh()->avatar_path;

        $this->deleteJson(self::URL)
            ->assertOk()
            ->assertJsonPath('message', 'Аватарка удалена.')
            ->assertJsonPath('data.avatar_url', null);

        self::assertNull($user->refresh()->avatar_path);
        Storage::disk('attachments')->assertMissing($path);
    }

    public function testWithoutAvatarSucceeds(): void
    {
        $this->actingAsUser();

        $this->deleteJson(self::URL)->assertOk()->assertJsonPath('data.avatar_url', null);
    }

    public function testRequiresAuthentication(): void
    {
        $this->deleteJson(self::URL)->assertUnauthorized();
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
