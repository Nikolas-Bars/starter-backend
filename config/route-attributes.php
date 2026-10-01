<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    'enabled' => true,

    // Маршруты объявляются атрибутами на контроллерах модулей (#[Post('auth/login')])
    'directories' => [
        app_path('Modules/*/Http/Controllers') => [
            'prefix'     => 'api',
            'middleware' => ['api'],
        ],
    ],

    'middleware' => [
        SubstituteBindings::class,
    ],

    'scope-bindings' => null,
];
