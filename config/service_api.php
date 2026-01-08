<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service API Settings
    |--------------------------------------------------------------------------
    | تنظیمات مربوط به API داخلی (برای Shop، Inventory و سایر سرویس‌ها)
    */

    'enabled' => env('ACCOUNTING_SERVICE_API_ENABLED', true),

    'prefix' => env('ACCOUNTING_SERVICE_API_PREFIX', 'api/v1/accounting'),

    'middleware' => ['api'],

    // API Key برای احراز هویت بین سرویس‌ها
    'api_key' => env('ACCOUNTING_SERVICE_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => env('ACCOUNTING_SERVICE_API_RATE_LIMIT', '120,1'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Sources
    |--------------------------------------------------------------------------
    | سیستم‌هایی که مجاز به فراخوانی Service API هستند
    */
    'trusted_sources' => [
        'SHOP',
        'INVENTORY',
        'ADMIN',
        'POS',
    ],
];
