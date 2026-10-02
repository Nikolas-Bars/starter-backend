<?php

declare(strict_types=1);

namespace App\Modules\Call\Tasks;

use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Date;

final class GenerateTurnCredentialsTask extends BaseTask
{
    /**
     * Временный логин TURN по схеме «TURN REST API»: coturn с --use-auth-secret сам проверяет
     * подпись и срок, хранить логины на сервере не нужно.
     *
     * @param  list<string>                                                    $urls
     * @return array{urls: list<string>, username: string, credential: string}
     */
    public function run(int $userId, array $urls, string $secret, int $ttlSeconds): array
    {
        $username = \sprintf('%d:%d', Date::now()->getTimestamp() + $ttlSeconds, $userId);

        return [
            'urls'       => $urls,
            'username'   => $username,
            'credential' => \base64_encode(\hash_hmac('sha1', $username, $secret, true)),
        ];
    }
}
