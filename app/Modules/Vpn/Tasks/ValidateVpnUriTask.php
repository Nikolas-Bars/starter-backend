<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Tasks;

use App\Tasks\BaseTask;

/**
 * Профиль VPN — одно из двух, и то и другое приложение отдаёт ядру Xray на телефоне:
 * - ссылка vless:// vmess:// trojan:// ss:// hysteria2:// (hy2://) socks:// — её разбирает ядро
 *   (libXray), транспорт и защита любые, какие оно знает (tcp, xhttp, ws, grpc, httpupgrade, kcp;
 *   reality, tls);
 * - JSON исходящих Xray: {"outbounds": [...]} или один outbound — применяется как есть, первый
 *   исходящий становится «proxy». Для того, чего нет в ссылках (фрагментация, цепочки).
 *
 * Здесь только проверка формы: настройки целиком проверит ядро, когда приложение проверяет профиль.
 */
final class ValidateVpnUriTask extends BaseTask
{
    public const array SCHEMES = ['vless', 'vmess', 'trojan', 'ss', 'hysteria2', 'hy2', 'socks'];

    public const int MAX_JSON_BYTES = 16384;

    /**
     * @return string|null Что не так; null — всё в порядке
     */
    public function run(string $uri): ?string
    {
        $uri = \trim($uri);

        return \str_starts_with($uri, '{') ? $this->validateJson($uri) : $this->validateLink($uri);
    }

    private function validateLink(string $uri): ?string
    {
        $prefix = \strstr($uri, '://', true);
        $scheme = $prefix === false ? '' : $prefix;

        if (!\in_array(\strtolower($scheme), self::SCHEMES, true)) {
            return 'нужна ссылка ' . \implode(':// ', self::SCHEMES) . ':// или JSON исходящих Xray';
        }

        if ($scheme !== \strtolower($scheme)) {
            return 'схему ссылки пишите маленькими буквами: ' . \strtolower($scheme) . '://';
        }

        $parts = \parse_url($uri);

        // Старый формат vmess://BASE64(JSON) ядро не понимает — только VMessAEAD с адресом в ссылке
        if ($scheme === 'vmess' && ($parts === false || ($parts['host'] ?? '') === '' || !isset($parts['user']))) {
            return 'vmess://BASE64 (старый формат) ядро не понимает: нужна ссылка вида vmess://uuid@host:port?…';
        }

        // У hysteria2 порт по умолчанию 443, у остальных он обязателен
        $portRequired = !\in_array($scheme, ['hysteria2', 'hy2'], true);

        if ($parts === false || ($parts['host'] ?? '') === '' || ($portRequired && !isset($parts['port']))) {
            return 'в ссылке нужны адрес и порт сервера: …@host:port';
        }

        if ($scheme !== 'socks' && ($parts['user'] ?? '') === '') {
            return 'перед @ должен быть ключ (UUID, пароль)';
        }

        return null;
    }

    private function validateJson(string $json): ?string
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            return 'JSON длиннее ' . self::MAX_JSON_BYTES . ' байт';
        }

        $data = \json_decode($json, true);

        if (!\is_array($data)) {
            return 'это не JSON';
        }

        $outbounds = \array_key_exists('outbounds', $data) ? $data['outbounds'] : [$data];

        if (!\is_array($outbounds) || $outbounds === [] || !\array_is_list($outbounds)) {
            return 'нужен {"outbounds": [ … ]} или один outbound';
        }

        foreach ($outbounds as $outbound) {
            if (!\is_array($outbound) || !\is_string($outbound['protocol'] ?? null) || $outbound['protocol'] === '') {
                return 'у каждого outbound должен быть protocol';
            }
        }

        return null;
    }
}
