<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateLocaleRequest",
 *     type="object",
 *     required={"locale"},
 *     @OA\Property(property="locale", type="string", enum={"ru", "vi", "en"}, example="vi", description="Один из app.supported_locales")
 * )
 */
final class UpdateLocaleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Config::array('app.supported_locales'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.profile_locale');
    }

    public function locale(): string
    {
        return $this->string('locale')->toString();
    }
}
