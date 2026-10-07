<?php

declare(strict_types=1);

// Те же правила, что в lang/ru/validation.php: новое правило — строка в обоих файлах
return [
    'required'  => 'Trường “:attribute” là bắt buộc.',
    'string'    => 'Trường “:attribute” phải là chuỗi ký tự.',
    'email'     => 'Trường “:attribute” phải là địa chỉ email hợp lệ.',
    'unique'    => 'Giá trị của trường “:attribute” đã được sử dụng.',
    'confirmed' => 'Trường “:attribute” không khớp với phần xác nhận.',
    'boolean'   => 'Trường “:attribute” phải là giá trị đúng hoặc sai.',
    'integer'   => 'Trường “:attribute” phải là số nguyên.',
    'exists'    => 'Giá trị đã chọn của trường “:attribute” không hợp lệ.',
    'in'        => 'Giá trị đã chọn của trường “:attribute” không hợp lệ.',
    'present'   => 'Trường “:attribute” phải có trong yêu cầu.',
    'regex'     => 'Trường “:attribute” không đúng định dạng.',
    'uuid'      => 'Trường “:attribute” phải là UUID.',
    'max'       => [
        'numeric' => 'Trường “:attribute” không được lớn hơn :max.',
        'string'  => 'Trường “:attribute” không được dài quá :max ký tự.',
        'array'   => 'Trường “:attribute” không được có quá :max phần tử.',
        'file'    => 'Tệp “:attribute” không được vượt quá :max KB.',
    ],
    'min' => [
        'numeric' => 'Trường “:attribute” không được nhỏ hơn :min.',
        'string'  => 'Trường “:attribute” phải có ít nhất :min ký tự.',
        'array'   => 'Trường “:attribute” phải có ít nhất :min phần tử.',
        'file'    => 'Tệp “:attribute” phải có kích thước ít nhất :min KB.',
    ],
    'password' => [
        'letters'       => 'Trường “:attribute” phải chứa ít nhất một chữ cái.',
        'mixed'         => 'Trường “:attribute” phải chứa cả chữ hoa và chữ thường.',
        'numbers'       => 'Trường “:attribute” phải chứa ít nhất một chữ số.',
        'symbols'       => 'Trường “:attribute” phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'Mật khẩu này đã xuất hiện trong các vụ rò rỉ dữ liệu. Hãy chọn mật khẩu khác.',
    ],

    'custom' => [
        'username' => [
            'regex' => 'Tên người dùng: chữ cái Latinh, chữ số và _, từ 3 đến 32 ký tự.',
        ],
    ],

    'attributes' => [],
];
