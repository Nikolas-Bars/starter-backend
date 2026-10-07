<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

final class UpdateLocaleControllerTest extends TestCase
{
    private const URL = '/api/profile/locale';

    public function testSavesLocaleAndAnswersInIt(): void
    {
        $user = $this->actingAsUser();

        $this->putJson(self::URL, ['locale' => 'vi'])
            ->assertOk()
            ->assertJsonPath('message', 'Đã lưu ngôn ngữ.')
            ->assertJsonPath('data.locale', 'vi');

        self::assertSame('vi', $user->refresh()->locale);
    }

    public function testRejectsUnsupportedLocale(): void
    {
        $user = $this->actingAsUser();

        foreach (['de', 'RU', ''] as $locale) {
            $this->putJson(self::URL, ['locale' => $locale])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('locale');
        }

        self::assertSame('ru', $user->refresh()->locale);
    }

    public function testIsOpenForGuestsButNotForAnonymous(): void
    {
        $this->putJson(self::URL, ['locale' => 'vi'])->assertUnauthorized();

        $host = User::factory()->create();
        $this->actingAsUser(User::factory()->create(['guest_of_id' => $host->id]));

        $this->putJson(self::URL, ['locale' => 'vi'])->assertOk();
    }

    public function testValidationErrorsFollowRequestLocale(): void
    {
        $this->actingAsUser();

        $this->withHeader('X-Locale', 'vi')
            ->putJson(self::URL, ['locale' => 'de'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Vui lòng kiểm tra lại các trường đã nhập.')
            ->assertJsonPath('errors.locale.0', 'Giá trị đã chọn của trường “ngôn ngữ” không hợp lệ.');
    }
}
