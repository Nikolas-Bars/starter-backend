<?php

declare(strict_types=1);

namespace App\Modules\Call\Tests\Unit;

use App\Modules\Call\WebSockets\AllowedOrigins;
use PHPUnit\Framework\TestCase;

final class AllowedOriginsTest extends TestCase
{
    public function testAddsMobileAppToSiteOrigins(): void
    {
        self::assertSame(
            ['http://localhost:5190', 'https://call-yansburg.com', 'starter-mobile://app'],
            AllowedOrigins::merge(
                ['http://localhost:5190', 'https://call-yansburg.com'],
                ['starter-mobile://app', 'http://localhost:5190'],
            ),
        );
    }

    public function testCheckStaysDisabledWithoutSiteOrigins(): void
    {
        self::assertSame([], AllowedOrigins::merge([], ['starter-mobile://app']));
    }
}
