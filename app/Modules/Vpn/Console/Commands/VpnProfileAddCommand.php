<?php

declare(strict_types=1);

namespace App\Modules\Vpn\Console\Commands;

use App\Modules\Vpn\Actions\AddVpnProfileAction;
use App\Modules\Vpn\DTO\VpnProfileDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class VpnProfileAddCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'vpn:profile:add
        {name : Название в приложении, например «Основной · TCP»}
        {--position=0 : Порядок в списке: меньше — выше}
        {--disabled : Добавить выключенным}
        {--stdin : Взять ссылку или JSON из stdin целиком (для многострочного JSON и pipe)}';

    /**
     * @var string
     */
    protected $description = 'Добавляет профиль VPN: ссылку (vless, vmess, trojan, ss, hysteria2, socks) или JSON исходящих Xray; спросит скрыто, чтобы ключ не попал в историю shell';

    public function handle(AddVpnProfileAction $action): int
    {
        $name     = \trim($this->argument('name'));
        $position = (string)$this->option('position');

        if ($name === '' || \mb_strlen($name) > 64) {
            $this->components->error('Название — от 1 до 64 символов');

            return self::FAILURE;
        }

        if (!\ctype_digit($position)) {
            $this->components->error('--position — целое число от 0');

            return self::FAILURE;
        }

        $uri = $this->readProfile();

        try {
            $profile = $action->run(new VpnProfileDTO(
                name: $name,
                uri: $uri,
                position: (int)$position,
                isEnabled: !$this->option('disabled'),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->components->error('Профиль не подходит: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Профиль #{$profile->id} «{$profile->name}» добавлен");

        return self::SUCCESS;
    }

    private function readProfile(): string
    {
        if ($this->option('stdin') === true) {
            $input = \stream_get_contents(\STDIN);

            return \is_string($input) ? \trim($input) : '';
        }

        $answer = $this->secret('Ссылка или JSON');

        return \is_string($answer) ? \trim($answer) : '';
    }
}
