<?php

declare(strict_types=1);

namespace App\Modules\Push\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="RegisterPushDeviceRequest",
 *     type="object",
 *     required={"token"},
 *     @OA\Property(property="token", type="string", maxLength=255, description="Токен Firebase Cloud Messaging", example="fGx2...:APA91b...")
 * )
 */
final class RegisterPushDeviceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.push_device');
    }

    public function token(): string
    {
        return $this->string('token')->trim()->toString();
    }
}
