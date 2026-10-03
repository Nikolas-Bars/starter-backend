<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\ChatFileRequestDTO;
use App\Services\FileUrlSigner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос от Caddy (forward_auth): исходная ссылка на файл — в заголовке X-Forwarded-Uri
 */
final class AuthorizeChatFileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDTO(): ChatFileRequestDTO
    {
        $uri   = $this->header('X-Forwarded-Uri', '');
        $path  = (string)\parse_url($uri, \PHP_URL_PATH);
        $query = [];
        \parse_str((string)\parse_url($uri, \PHP_URL_QUERY), $query);

        return new ChatFileRequestDTO(
            path: \str_starts_with($path, FileUrlSigner::PREFIX) ? \rawurldecode(\substr($path, \strlen(FileUrlSigner::PREFIX))) : '',
            expires: \is_string($query['expires'] ?? null) ? $query['expires'] : '',
            signature: \is_string($query['signature'] ?? null) ? $query['signature'] : '',
        );
    }
}
