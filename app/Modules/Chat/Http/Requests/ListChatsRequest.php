<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Parameter(
 *     parameter="ListChatsRequest.folder_id",
 *     name="folder_id",
 *     in="query",
 *     required=false,
 *     description="Только чаты этой папки. Без него — все чаты",
 *     @OA\Schema(type="integer", minimum=1, example=3)
 * )
 */
final class ListChatsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_list');
    }

    public function folderId(): ?int
    {
        return $this->filled('folder_id') ? $this->integer('folder_id') : null;
    }
}
