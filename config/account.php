<?php

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'lester@hurtado.ph'),
        'identifier_column' => 'email',
        'model' => env('ACCOUNT_SYSTEM_USER_MODEL', class_exists(\App\Models\User::class)
            ? \App\Models\User::class
            : null),
    ],
];
