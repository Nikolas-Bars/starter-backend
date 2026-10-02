<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="OpenDirectChatRequest",
 *     type="object",
 *     required={"user_id"},
 *     @OA\Property(property="user_id", type="integer", example=2, description="С кем переписка")
 * )
 */
final class OpenDirectChatRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_open');
    }

    public function peerId(): int
    {
        return $this->integer('user_id');
    }
}
