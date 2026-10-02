<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        env('APP_ENV', 'production') === 'production'
            ? sys_get_temp_dir()
            : realpath(storage_path('framework/views')),
    ),
];
