<?php

declare(strict_types=1);

return [
    'validation'        => 'Vui lòng kiểm tra lại các trường đã nhập.',
    'unauthenticated'   => 'Bạn cần đăng nhập.',
    'too_many_requests' => 'Quá nhiều lần thử. Vui lòng thử lại sau một phút.',
    'not_found'         => 'Không tìm thấy dữ liệu.',
    'http_error'        => 'Lỗi yêu cầu.',

    'auth' => [
        'invalid_credentials' => 'Email hoặc mật khẩu không đúng.',
    ],

    'call' => [
        'not_found'       => 'Không tìm thấy cuộc gọi.',
        'not_participant' => 'Bạn không tham gia cuộc gọi này.',
        'already_in_call' => 'Hãy kết thúc cuộc gọi hiện tại trước.',
        'invalid_state'   => 'Cuộc gọi đã kết thúc hoặc chưa bắt đầu.',
        'invalid_callee'  => 'Không thể gọi cho người dùng này.',
        'bad_message'     => 'Tin nhắn không hợp lệ.',
        'server_error'    => 'Lỗi máy chủ cuộc gọi. Vui lòng thử lại.',
    ],

    'call_link' => [
        'not_found' => 'Liên kết cuộc gọi không còn hiệu lực. Hãy nhờ gửi liên kết mới.',
    ],

    'chat' => [
        'not_found'            => 'Không tìm thấy cuộc trò chuyện.',
        'invalid_peer'         => 'Không thể nhắn tin cho người dùng này.',
        'message_not_found'    => 'Không tìm thấy tin nhắn.',
        'message_forbidden'    => 'Bạn chỉ có thể xóa tin nhắn của mình.',
        'not_forwardable'      => 'Không thể chuyển tiếp tin nhắn này.',
        'not_editable'         => 'Bạn chỉ có thể sửa tin nhắn của mình.',
        'answered'             => 'Tin nhắn đã được trả lời — không thể sửa nữa.',
        'folder_not_found'     => 'Không tìm thấy thư mục.',
        'folder_limit'         => 'Không thể tạo quá 20 thư mục.',
        'attachment_not_found' => 'Không tìm thấy tệp hoặc tệp đã được gửi.',
        'storage_full'         => 'Đã hết dung lượng lưu trữ tệp. Vui lòng thử lại sau.',
        'file_forbidden'       => 'Liên kết tới tệp đã hết hạn hoặc không hợp lệ.',
    ],

    'user' => [
        'guest_forbidden' => 'Khách không dùng được chức năng này. Hãy đăng ký để tiếp tục.',
        'avatar_invalid'  => 'Không đọc được ảnh. Hãy chọn ảnh khác.',
    ],
];
