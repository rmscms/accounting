<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin API Settings
    |--------------------------------------------------------------------------
    | تنظیمات مربوط به API پنل ادمین
    */

    'enabled' => env('ACCOUNTING_ADMIN_API_ENABLED', true),

    'prefix' => env('ACCOUNTING_ADMIN_API_PREFIX', 'api/v1/admin/accounting'),

    'middleware' => ['api'],

    'auth_guard' => env('ACCOUNTING_ADMIN_API_AUTH_GUARD', 'sanctum'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => env('ACCOUNTING_ADMIN_API_RATE_LIMIT', '60,1'),
];
