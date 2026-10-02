<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Modules\User\DTO\ListUsersDTO;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Parameter(
 *     parameter="ListUsersRequest.search",
 *     name="search",
 *     in="query",
 *     required=false,
 *     description="Часть имени, email или ника (можно с @)",
 *     @OA\Schema(type="string", maxLength=100, example="иван")
 * )
 */
final class ListUsersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.user_list');
    }

    public function toDTO(): ListUsersDTO
    {
        // «@ivan» ищет по нику так же, как «ivan»
        $search = $this->string('search')->trim()->ltrim('@')->toString();

        return new ListUsersDTO(search: $search === '' ? null : $search);
    }
}
