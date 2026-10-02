<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Enums\ChatReactionEnum;
use App\Services\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="ReactToMessageRequest",
 *     type="object",
 *     required={"emoji"},
 *     @OA\Property(property="emoji", type="string", enum={"👍", "❤️", "😂", "😮", "😢", "🔥", "🙏", "👎"}, example="👍")
 * )
 */
final class ReactToMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::enum(ChatReactionEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Translator::group('fields.chat_reaction');
    }

    public function emoji(): ChatReactionEnum
    {
        return ChatReactionEnum::from($this->string('emoji')->toString());
    }
}
