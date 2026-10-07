<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\Enums\ChatMessageTypeEnum;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Tasks\FindChatMessageForTranslationTask;
use App\Modules\Chat\Tasks\ListChatParticipantsTask;
use App\Modules\Chat\Tasks\ListTranslationContextTask;
use App\Modules\Chat\Tasks\PublishChatEventTask;
use App\Modules\Chat\Tasks\SaveChatMessageTranslationsTask;
use App\Services\Translation\TranslationFailedException;
use App\Services\Translation\TranslationRequest;
use App\Services\Translation\TranslatorFactory;

final class TranslateChatMessageAction extends BaseAction
{
    public const EVENT = 'chat.message_translated';

    public function __construct(
        private readonly TranslatorFactory                 $translatorFactory,
        private readonly FindChatMessageForTranslationTask $findChatMessageForTranslationTask,
        private readonly ListChatParticipantsTask          $listChatParticipantsTask,
        private readonly ListTranslationContextTask        $listTranslationContextTask,
        private readonly SaveChatMessageTranslationsTask   $saveChatMessageTranslationsTask,
        private readonly PublishChatEventTask              $publishChatEventTask,
    ) {
    }

    /**
     * Переводит текст сообщения на языки интерфейса остальных участников с учётом последних
     * сообщений и заметки к чату, сохраняет и рассылает участникам chat.message_translated.
     * Если все говорят на языке автора, нейросеть не вызывается.
     *
     * @throws TranslationFailedException Очередь повторит задачу
     */
    public function run(int $messageId): void
    {
        $message    = $this->findChatMessageForTranslationTask->run($messageId);
        $translator = $this->translatorFactory->make();

        if ($message === null || $translator === null || $message->type !== ChatMessageTypeEnum::Text || $message->body === '') {
            return;
        }

        $members = $this->listChatParticipantsTask->run($message->chat_id);
        $author  = null;
        $names   = [];
        $targets = [];

        foreach ($members as $member) {
            $names[$member->user_id] = $member->user->name;

            if ($member->user_id === $message->user_id) {
                $author = $member;
            } else {
                $targets[$member->user->locale] = true;
            }
        }

        // Говорят на одном языке: переводить нечего, а каждый вызов нейросети стоит денег
        if ($author !== null) {
            unset($targets[$author->user->locale]);
        }

        if ($targets === []) {
            return;
        }

        $body    = $message->body;
        $request = new TranslationRequest(
            text: $body,
            author: $message->forwarded_from_name ?? $names[$message->user_id] ?? '',
            targets: \array_keys($targets),
            participants: \array_map(
                static fn(ChatMember $member): array => ['name' => $member->user->name, 'locale' => $member->user->locale],
                $members,
            ),
            context: $this->listTranslationContextTask->run($message, $names),
            note: $message->chat->translation_note,
        );

        $result = $translator->translate($request);

        // Пока нейросеть думала, текст могли изменить или сообщение удалить: правка поставит свой перевод
        $message = $this->findChatMessageForTranslationTask->run($messageId);

        if ($message === null || $message->body !== $body) {
            return;
        }

        $message = $this->saveChatMessageTranslationsTask->run($message, $result->sourceLocale, $result->translations);

        $this->publishChatEventTask->run(\array_keys($names), self::EVENT, [
            'chat_id'      => $message->chat_id,
            'message_id'   => $message->id,
            'body_locale'  => $message->body_locale,
            'translations' => (object)$message->translations->pluck('body', 'locale')->all(),
        ]);
    }
}
