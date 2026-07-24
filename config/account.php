<?php

use App\Models\User;

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'lester@hurtado.ph'),
        'identifier_column' => 'email',
        'model' => env('ACCOUNT_SYSTEM_USER_MODEL', class_exists(User::class)
            ? User::class
            : null),

        /*
         * Named candidates allow an integrating package to provide one or more
         * stable ways to locate the same system principal. When populated,
         * these candidates replace the legacy fields above. Candidates that
         * resolve to different wallets fail closed.
         */
        'candidates' => [],
    ],
];
