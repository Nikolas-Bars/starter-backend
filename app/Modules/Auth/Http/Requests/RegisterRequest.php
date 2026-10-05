<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTO\RegisterDTO;
use App\Modules\User\Http\Requests\UpdateProfileRequest;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @OA\Schema(
 *     schema="RegisterRequest",
 *     type="object",
 *     required={"name", "username", "email", "password", "password_confirmation"},
 *     @OA\Property(property="name", type="string", maxLength=255, example="Иван Петров"),
 *     @OA\Property(
 *         property="username",
 *         type="string",
 *         pattern="^[a-z0-9_]{3,32}$",
 *         example="ivan_petrov",
 *         description="Ник для поиска: латиница, цифры и _, от 3 до 32 символов. Регистр и ведущий @ не важны"
 *     ),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="new-user@example.com"),
 *     @OA\Property(property="password", type="string", format="password", minLength=8, example="Password123"),
 *     @OA\Property(property="password_confirmation", type="string", format="password", example="Password123"),
 *     @OA\Property(property="device_name", type="string", maxLength=255, nullable=true, example="web")
 * )
 */
final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['required', 'string', 'regex:' . UpdateProfileRequest::USERNAME_PATTERN, 'unique:users,username'],
            'email'       => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.register');
    }

    public function toDTO(): RegisterDTO
    {
        return new RegisterDTO(
            name: $this->string('name')->trim()->toString(),
            username: $this->string('username')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }

    public function deviceName(): string
    {
        return $this->string('device_name', 'web')->toString();
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('email'))) {
            $this->merge(['email' => \mb_strtolower(\trim($this->input('email')))]);
        }

        if (\is_string($this->input('username'))) {
            $this->merge(['username' => \mb_strtolower(\ltrim(\trim($this->input('username')), '@'))]);
        }
    }
}
