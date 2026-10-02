<?php

declare(strict_types=1);

namespace App\Modules\CallLink\Http\Requests;

use App\Modules\CallLink\DTO\JoinCallLinkDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="JoinCallLinkRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", minLength=1, maxLength=60, example="Аркадий"),
 *     @OA\Property(property="device_name", type="string", maxLength=255, nullable=true, example="web")
 * )
 */
final class JoinCallLinkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:60'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.call_link_join');
    }

    public function toDTO(): JoinCallLinkDTO
    {
        return new JoinCallLinkDTO(
            name: $this->string('name')->toString(),
            device_name: $this->string('device_name', 'web')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        if (\is_string($this->input('name'))) {
            $this->merge(['name' => \trim($this->input('name'))]);
        }
    }
}
