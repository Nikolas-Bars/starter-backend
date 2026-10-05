<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Actions\BaseAction;
use App\Modules\User\Exceptions\InvalidAvatarException;
use App\Modules\User\Models\User;
use App\Modules\User\Tasks\SetUserAvatarTask;
use App\Modules\User\Tasks\StoreUserAvatarTask;
use Illuminate\Http\UploadedFile;

final class UpdateAvatarAction extends BaseAction
{
    public function __construct(
        private readonly StoreUserAvatarTask $storeUserAvatarTask,
        private readonly SetUserAvatarTask   $setUserAvatarTask,
    ) {
    }

    /**
     * @throws InvalidAvatarException
     */
    public function run(User $user, UploadedFile $file): User
    {
        return $this->setUserAvatarTask->run($user, $this->storeUserAvatarTask->run($file));
    }
}
