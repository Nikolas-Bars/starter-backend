<?php

declare(strict_types=1);

return [
    'register' => [
        'name'        => 'name',
        'username'    => 'username',
        'email'       => 'email',
        'password'    => 'password',
        'device_name' => 'device name',
    ],
    'login' => [
        'email'       => 'email',
        'password'    => 'password',
        'device_name' => 'device name',
    ],
    'user_list' => [
        'search' => 'search',
    ],
    'profile' => [
        'name'     => 'name',
        'username' => 'username',
        'avatar'   => 'avatar',
    ],
    'chat_open' => [
        'user_id' => 'recipient',
    ],
    'chat_message' => [
        'body'             => 'message text',
        'client_id'        => 'message id',
        'attachment_ids'   => 'files',
        'attachment_ids.*' => 'file',
    ],
    'chat_attachment' => [
        'file'    => 'file',
        'name'    => 'file name',
        'voice'   => 'voice message',
        'as_file' => 'uncompressed',
    ],
    'chat_read' => [
        'message_id' => 'message',
    ],
    'chat_history' => [
        'before_id' => 'message',
    ],
    'chat_list' => [
        'folder_id' => 'folder',
        'page'      => 'page',
    ],
    'chat_forward' => [
        'message_id' => 'message',
        'client_id'  => 'message identifier',
    ],
    'chat_reaction' => [
        'emoji' => 'reaction',
    ],
    'chat_folder' => [
        'name' => 'folder name',
    ],
    'call_link_join' => [
        'name'        => 'name',
        'device_name' => 'device name',
    ],
    'push_device' => [
        'token' => 'device token',
    ],
    'call_decline' => [
        'token' => 'decline key',
    ],
];
