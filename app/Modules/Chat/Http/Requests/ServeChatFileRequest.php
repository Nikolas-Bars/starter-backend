<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\DTO\ChatFileRequestDTO;
use Illuminate\Foundation\Http\FormRequest;

final class ServeChatFileRequest extends FormRequest
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
        $path = $this->route('path');

        return new ChatFileRequestDTO(
            path: \is_string($path) ? $path : '',
            expires: $this->string('expires')->toString(),
            signature: $this->string('signature')->toString(),
        );
    }
}
