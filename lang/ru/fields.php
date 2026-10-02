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
    'user_list' => [
        'search' => 'поиск',
    ],
    'profile' => [
        'name'     => 'имя',
        'username' => 'ник',
    ],
    'chat_open' => [
        'user_id' => 'собеседник',
    ],
    'chat_message' => [
        'body'      => 'текст сообщения',
        'client_id' => 'идентификатор сообщения',
    ],
    'chat_read' => [
        'message_id' => 'сообщение',
    ],
    'chat_history' => [
        'before_id' => 'сообщение',
    ],
    'chat_list' => [
        'folder_id' => 'папка',
        'page'      => 'страница',
    ],
    'chat_reaction' => [
        'emoji' => 'реакция',
    ],
    'chat_folder' => [
        'name' => 'название папки',
    ],
    'call_link_join' => [
        'name'        => 'имя',
        'device_name' => 'название устройства',
    ],
    'push_device' => [
        'token' => 'токен устройства',
    ],
    'call_decline' => [
        'token' => 'ключ отклонения',
    ],
];
