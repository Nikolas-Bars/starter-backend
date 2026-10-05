<?php

declare(strict_types=1);

namespace App\Modules\User\Tasks;

use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Tasks\BaseTask;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class SetUserAvatarTask extends BaseTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    /**
     * Ставит новую аватарку (null — убирает) и удаляет файл прежней
     */
    public function run(User $user, ?string $path): User
    {
        $previous = $user->avatar_path;

        $this->repository->updateAvatar($user, $path);

        if ($previous !== null && $previous !== $path) {
            Storage::disk(Config::string('attachments.disk'))->delete($previous);
        }

        return $user;
    }
}
