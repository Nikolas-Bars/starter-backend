<?php

declare(strict_types=1);

return [
    'validation'        => 'Please check the highlighted fields.',
    'unauthenticated'   => 'Authentication required.',
    'too_many_requests' => 'Too many attempts. Try again in a minute.',
    'not_found'         => 'Record not found.',
    'http_error'        => 'Request error.',

    'auth' => [
        'invalid_credentials' => 'Invalid email or password.',
    ],

    'call' => [
        'not_found'       => 'Call not found.',
        'not_participant' => 'You are not a participant of this call.',
        'already_in_call' => 'Finish your current call first.',
        'invalid_state'   => 'The call has already ended or has not started yet.',
        'invalid_callee'  => 'You cannot call this user.',
        'bad_message'     => 'Malformed message.',
        'server_error'    => 'Call server error. Please try again.',
    ],

    'call_link' => [
        'not_found' => 'This call link is no longer valid. Ask for a new one.',
    ],

    'chat' => [
        'not_found'         => 'Chat not found.',
        'invalid_peer'      => 'You cannot message this user.',
        'message_not_found' => 'Message not found.',
        'folder_not_found'  => 'Folder not found.',
        'folder_limit'      => 'You can have at most 20 folders.',
    ],

    'user' => [
        'guest_forbidden' => 'Not available for guests. Sign up to continue.',
    ],
];
