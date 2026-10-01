<?php

declare(strict_types=1);

// Названия полей сгруппированы по формам: одно имя поля в разных запросах может значить разное
return [
    'register' => [
        'name'        => 'имя',
        'email'       => 'email',
        'password'    => 'пароль',
        'device_name' => 'название устройства',
    ],
    'login' => [
        'email'       => 'email',
        'password'    => 'пароль',
        'device_name' => 'название устройства',
    ],
];
