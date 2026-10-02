<?php

declare(strict_types=1);

namespace App\Modules\Chat\Tasks;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Repositories\ChatMemberRepository;
use App\Modules\Chat\Repositories\ChatRepository;
use App\Tasks\BaseTask;

final class CreateDirectChatTask extends BaseTask
{
    public function __construct(
        private readonly ChatRepository       $chatRepository,
        private readonly ChatMemberRepository $memberRepository,
    ) {
    }

    /**
     * Вызывать внутри транзакции: чат без участников бесполезен
     */
    public function run(int $firstUserId, int $secondUserId): Chat
    {
        $chat = $this->chatRepository->storeDirect(Chat::directKey($firstUserId, $secondUserId));

        $this->memberRepository->store($chat->id, $firstUserId);
        $this->memberRepository->store($chat->id, $secondUserId);

        return $chat;
    }
}
