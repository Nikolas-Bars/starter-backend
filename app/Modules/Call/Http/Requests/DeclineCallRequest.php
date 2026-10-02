<?php

declare(strict_types=1);

namespace App\Modules\Call\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="DeclineCallRequest",
 *     type="object",
 *     required={"token"},
 *     @OA\Property(property="token", type="string", description="Ключ decline_token из push о входящем вызове", example="3f6c...e91a")
 * )
 */
final class DeclineCallRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.call_decline');
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }
}
