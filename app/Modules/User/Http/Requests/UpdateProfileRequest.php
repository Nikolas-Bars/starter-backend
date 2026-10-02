<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Modules\User\DTO\UpdateProfileDTO;
use App\Modules\User\Models\User;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateProfileRequest",
 *     type="object",
 *     required={"name", "username"},
 *     @OA\Property(property="name", type="string", maxLength=255, example="Иван Петров"),
 *     @OA\Property(
 *         property="username",
 *         type="string",
 *         nullable=true,
 *         pattern="^[a-z0-9_]{3,32}$",
 *         example="ivan_petrov",
 *         description="Латиница, цифры и _, от 3 до 32 символов. Регистр и ведущий @ не важны. null — убрать ник"
 *     )
 * )
 */
final class UpdateProfileRequest extends FormRequest
{
    public const USERNAME_PATTERN = '/^[a-z0-9_]{3,32}$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['present', 'nullable', 'string', 'regex:' . self::USERNAME_PATTERN, Rule::unique('users', 'username')->ignore($user->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.profile');
    }

    public function toDTO(): UpdateProfileDTO
    {
        $username = $this->input('username');

        return new UpdateProfileDTO(
            name: $this->string('name')->toString(),
            username: \is_string($username) && $username !== '' ? $username : null,
        );
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('name'))) {
            $this->merge(['name' => \trim($this->input('name'))]);
        }

        if (\is_string($this->input('username'))) {
            $username = \mb_strtolower(\ltrim(\trim($this->input('username')), '@'));
            $this->merge(['username' => $username === '' ? null : $username]);
        }
    }
}
