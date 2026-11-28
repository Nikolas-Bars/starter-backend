<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tests\Unit;

use App\Modules\Vpn\Tasks\ValidateVpnUriTask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidateVpnUriTaskTest extends TestCase
{
    private const string UUID = '6f1c2b0e-8a3d-4c1b-9e7a-2d5f4b3c1a90';

    /**
     * @return iterable<string, array{string}>
     */
    public static function validProfiles(): iterable
    {
        $uuid = self::UUID;

        yield 'vless reality tcp' => ["vless://{$uuid}@185.17.1.109:2083?type=tcp&security=reality&pbk=KEY&sid=ab&sni=www.samsung.com&flow=xtls-rprx-vision#TCP"];
        yield 'vless xhttp' => ["vless://{$uuid}@185.17.1.109:2053?type=xhttp&security=reality&pbk=KEY&sni=a.com&path=%2Fabc"];
        yield 'vless ws tls' => ["vless://{$uuid}@cdn.example.com:443?type=ws&security=tls&path=%2Fws&host=cdn.example.com"];
        yield 'vmess grpc' => ["vmess://{$uuid}@host.example:443?type=grpc&security=tls&serviceName=svc"];
        yield 'trojan' => ['trojan://secret@host.example:443?security=tls&sni=host.example'];
        yield 'shadowsocks' => ['ss://MjAyMi1ibGFrZTMtYWVzLTI1Ni1nY206cGFzcw@host.example:8388#ss'];
        yield 'hysteria2' => ['hysteria2://auth@host.example:443?sni=host.example'];
        yield 'hy2 default port' => ['hy2://auth@host.example/?sni=host.example'];
        yield 'socks' => ['socks://host.example:1080'];
        yield 'json outbounds' => ['{"outbounds":[{"protocol":"vless","settings":{}},{"tag":"frag","protocol":"freedom","settings":{"fragment":{"packets":"tlshello"}}}]}'];
        yield 'json one outbound' => ['{"protocol":"trojan","settings":{"servers":[]}}'];
    }

    #[DataProvider('validProfiles')]
    public function testAccepts(string $uri): void
    {
        self::assertNull((new ValidateVpnUriTask())->run($uri));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProfiles(): iterable
    {
        $uuid = self::UUID;

        yield 'https' => ['https://example.com'];
        yield 'wireguard' => ["wireguard://{$uuid}@host:51820"];
        yield 'no port' => ["vless://{$uuid}@host?security=tls"];
        yield 'no key' => ['trojan://host.example:443'];
        yield 'legacy vmess' => ['vmess://eyJ2IjoiMiIsImFkZCI6Imhvc3QifQ=='];
        yield 'upper-case scheme' => ["VLESS://{$uuid}@host:443?security=tls"];
        yield 'broken json' => ['{"outbounds": ['];
        yield 'empty outbounds' => ['{"outbounds": []}'];
        yield 'no protocol' => ['{"outbounds": [{"tag": "proxy"}]}'];
        yield 'too long json' => ['{"protocol":"freedom","x":"' . \str_repeat('a', 17000) . '"}'];
    }

    #[DataProvider('invalidProfiles')]
    public function testRejects(string $uri): void
    {
        self::assertIsString((new ValidateVpnUriTask())->run($uri));
    }
}
