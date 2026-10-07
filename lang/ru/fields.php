<?php

declare(strict_types=1);

// Названия полей сгруппированы по формам: одно имя поля в разных запросах может значить разное
return [
    'register' => [
        'name'        => 'имя',
        'username'    => 'ник',
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
        'avatar'   => 'аватарка',
    ],
    'profile_locale' => [
        'locale' => 'язык',
    ],
    'chat_open' => [
        'user_id' => 'собеседник',
    ],
    'chat_message' => [
        'body'             => 'текст сообщения',
        'client_id'        => 'идентификатор сообщения',
        'attachment_ids'   => 'файлы',
        'attachment_ids.*' => 'файл',
    ],
    'chat_attachment' => [
        'file'    => 'файл',
        'name'    => 'имя файла',
        'voice'   => 'голосовое',
        'as_file' => 'без сжатия',
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
    'chat_forward' => [
        'message_id' => 'сообщение',
        'client_id'  => 'идентификатор сообщения',
    ],
    'chat_reaction' => [
        'emoji' => 'реакция',
    ],
    'chat_folder' => [
        'name' => 'название папки',
    ],
    'chat_translation_note' => [
        'note' => 'заметка для перевода',
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
