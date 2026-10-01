<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Tasks\VerifyPasswordTask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class VerifyPasswordTaskTest extends TestCase
{
    public function testAcceptsCorrectPassword(): void
    {
        $user = new User(['password' => Hash::make('Secret123')]);

        self::assertTrue((new VerifyPasswordTask())->run($user, 'Secret123'));
    }

    public function testRejectsWrongPassword(): void
    {
        $user = new User(['password' => Hash::make('Secret123')]);

        self::assertFalse((new VerifyPasswordTask())->run($user, 'Secret124'));
    }

    public function testRejectsMissingUser(): void
    {
        self::assertFalse((new VerifyPasswordTask())->run(null, 'Secret123'));
    }
}
