<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTO\LoginDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="admin@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="Password123"),
 *     @OA\Property(property="device_name", type="string", maxLength=255, nullable=true, example="web")
 * )
 */
final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email'       => ['required', 'string', 'email', 'max:255'],
            'password'    => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.login');
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            device_name: $this->string('device_name', 'web')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('email'))) {
            $this->merge(['email' => \mb_strtolower(\trim($this->input('email')))]);
        }
    }
}
