<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Console\Commands;

use App\Modules\Vpn\Actions\UpdateVpnProfileAction;
use App\Modules\Vpn\DTO\VpnProfileChangesDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class VpnProfileEditCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'vpn:profile:edit
        {id : id из vpn:profiles}
        {--name= : Новое название}
        {--uri : Спросить новую ссылку или JSON (скрыто)}
        {--stdin : С --uri: взять ссылку или JSON из stdin целиком}
        {--position= : Новый порядок}
        {--enable : Включить}
        {--disable : Выключить: клиентам профиль больше не отдаётся}';

    /**
     * @var string
     */
    protected $description = 'Меняет профиль VPN: название, ссылку, порядок, включён ли';

    public function handle(UpdateVpnProfileAction $action): int
    {
        if ($this->option('enable') === true && $this->option('disable') === true) {
            $this->components->error('--enable и --disable вместе не бывают');

            return self::FAILURE;
        }

        $name     = $this->option('name');
        $position = $this->option('position');
        $uri      = null;

        if ($this->option('stdin') === true && $this->option('uri') !== true) {
            $this->components->error('--stdin работает только вместе с --uri');

            return self::FAILURE;
        }

        if (\is_string($name) && \mb_strlen(\trim($name)) > 64) {
            $this->components->error('Название — до 64 символов');

            return self::FAILURE;
        }

        if (\is_string($position) && $position !== '' && !\ctype_digit($position)) {
            $this->components->error('--position — целое число от 0');

            return self::FAILURE;
        }

        if ($this->option('uri') === true) {
            $answer = $this->option('stdin') === true ? \stream_get_contents(\STDIN) : $this->secret('Новая ссылка или JSON');
            $uri    = \is_string($answer) ? \trim($answer) : '';
        }

        $changes = new VpnProfileChangesDTO(
            name: \is_string($name) && \trim($name) !== '' ? \trim($name) : null,
            uri: $uri,
            position: \is_string($position) && $position !== '' ? (int)$position : null,
            isEnabled: match (true) {
                $this->option('enable') === true  => true,
                $this->option('disable') === true => false,
                default                           => null,
            },
        );

        if ($changes->attributes() === []) {
            $this->components->warn('Нечего менять: укажите --name, --uri, --position, --enable или --disable');

            return self::FAILURE;
        }

        try {
            $profile = $action->run((int)$this->argument('id'), $changes);
        } catch (InvalidArgumentException $exception) {
            $this->components->error('Не получилось: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Профиль #{$profile->id} «{$profile->name}» обновлён");

        return self::SUCCESS;
    }
}
