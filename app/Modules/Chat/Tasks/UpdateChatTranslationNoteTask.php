<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class UpdateChatTranslationNoteTask extends BaseTask
{
    public function __construct(
        private readonly ChatRepository $repository,
    ) {
    }

    /**
     * @return bool Заметка изменилась
     */
    public function run(Chat $chat, ?string $note): bool
    {
        $this->repository->updateTranslationNote($chat, $note);

        return $chat->wasChanged('translation_note');
    }
}
