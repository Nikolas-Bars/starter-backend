<?php

declare(strict_types=1);

// Названия полей сгруппированы по формам: одно имя поля в разных запросах может значить разное
return [
    'register' => [
        'name'        => 'tên',
        'username'    => 'tên người dùng',
        'email'       => 'email',
        'password'    => 'mật khẩu',
        'device_name' => 'tên thiết bị',
    ],
    'login' => [
        'email'       => 'email',
        'password'    => 'mật khẩu',
        'device_name' => 'tên thiết bị',
    ],
    'user_list' => [
        'search' => 'tìm kiếm',
    ],
    'profile' => [
        'name'     => 'tên',
        'username' => 'tên người dùng',
        'avatar'   => 'ảnh đại diện',
    ],
    'profile_locale' => [
        'locale' => 'ngôn ngữ',
    ],
    'chat_open' => [
        'user_id' => 'người nhận',
    ],
    'chat_message' => [
        'body'             => 'nội dung tin nhắn',
        'client_id'        => 'mã tin nhắn',
        'attachment_ids'   => 'tệp',
        'attachment_ids.*' => 'tệp',
    ],
    'chat_attachment' => [
        'file'    => 'tệp',
        'name'    => 'tên tệp',
        'voice'   => 'tin nhắn thoại',
        'as_file' => 'không nén',
    ],
    'chat_read' => [
        'message_id' => 'tin nhắn',
    ],
    'chat_history' => [
        'before_id' => 'tin nhắn',
    ],
    'chat_list' => [
        'folder_id' => 'thư mục',
        'page'      => 'trang',
    ],
    'chat_forward' => [
        'message_id' => 'tin nhắn',
        'client_id'  => 'mã tin nhắn',
    ],
    'chat_reaction' => [
        'emoji' => 'biểu cảm',
    ],
    'chat_folder' => [
        'name' => 'tên thư mục',
    ],
    'call_link_join' => [
        'name'        => 'tên',
        'device_name' => 'tên thiết bị',
    ],
    'push_device' => [
        'token' => 'mã thiết bị',
    ],
    'call_decline' => [
        'token' => 'mã từ chối',
    ],
];
