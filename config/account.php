<?php

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'lester@hurtado.ph'),
        'identifier_column' => 'email',
        'model' => class_exists(App\Models\User::class)
            ? App\Models\User::class
            : LBHurtado\Wallet\Tests\Models\User::class,
    ],
];
