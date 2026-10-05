<?php

declare(strict_types=1);

namespace App\Modules\Chat\Actions;

use App\Actions\BaseAction;
use App\Modules\Chat\DTO\ChatFileAccessDTO;
use App\Modules\Chat\DTO\ChatFileRequestDTO;
use App\Modules\Chat\Enums\ChatAttachmentKindEnum;
use App\Modules\Chat\Enums\ChatAttachmentStatusEnum;
use App\Modules\Chat\Exceptions\ChatFileForbiddenException;
use App\Modules\Chat\Tasks\FindChatAttachmentByPathTask;
use App\Modules\User\Tasks\FindUserByAvatarPathTask;
use App\Services\FileUrlSigner;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class AuthorizeChatFileAction extends BaseAction
{
    public function __construct(
        private readonly FileUrlSigner                $fileUrlSigner,
        private readonly FindChatAttachmentByPathTask $findChatAttachmentByPathTask,
        private readonly FindUserByAvatarPathTask     $findUserByAvatarPathTask,
    ) {
    }

    /**
     * Проверяет подписанную ссылку и говорит, с какими заголовками отдать файл.
     * Ссылку получают только участники чата (в истории сообщений) — сама подпись и есть доступ.
     * Так же отдаются аватарки: ссылку на них видят все, кому виден пользователь.
     *
     * @throws ChatFileForbiddenException
     */
    public function run(ChatFileRequestDTO $dto): ChatFileAccessDTO
    {
        if (!$this->fileUrlSigner->verify($dto->path, $dto->expires, $dto->signature)) {
            throw new ChatFileForbiddenException();
        }

        if ($this->findUserByAvatarPathTask->run($dto->path) !== null) {
            return new ChatFileAccessDTO(
                path: $dto->path,
                content_type: 'image/jpeg',
                content_disposition: HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'avatar.jpg'),
            );
        }

        $attachment = $this->findChatAttachmentByPathTask->run($dto->path);

        if ($attachment === null || $attachment->status !== ChatAttachmentStatusEnum::Ready) {
            throw new ChatFileForbiddenException();
        }

        $isThumb = $dto->path === $attachment->thumb_path;
        $isFile  = !$isThumb && $attachment->kind === ChatAttachmentKindEnum::File;
        $name    = $isThumb ? 'preview.jpg' : $attachment->original_name;

        return new ChatFileAccessDTO(
            path: $dto->path,
            content_type: match (true) {
                $isThumb => 'image/jpeg',
                $isFile  => 'application/octet-stream',
                default  => $attachment->mime,
            },
            content_disposition: HeaderUtils::makeDisposition(
                $isFile ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $name,
                $this->asciiFallback($name),
            ),
        );
    }

    private function asciiFallback(string $name): string
    {
        $ascii = (string)\preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($name));

        return \trim($ascii, '_') === '' ? 'file' : $ascii;
    }
}
