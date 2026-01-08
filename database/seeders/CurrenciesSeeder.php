<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Models\Currency;

/**
 * Seeder ارزهای پیش‌فرض
 */
class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'IRR',
                'name' => 'ریال ایران',
                'symbol' => 'ریال',
                'is_base' => true,
                'active' => true,
            ],
            [
                'code' => 'USD',
                'name' => 'دلار آمریکا',
                'symbol' => '$',
                'is_base' => false,
                'active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'یورو',
                'symbol' => '€',
                'is_base' => false,
                'active' => true,
            ],
            [
                'code' => 'AED',
                'name' => 'درهم امارات',
                'symbol' => 'AED',
                'is_base' => false,
                'active' => true,
            ],
            [
                'code' => 'TRY',
                'name' => 'لیر ترکیه',
                'symbol' => '₺',
                'is_base' => false,
                'active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::create($currency);
        }

        $this->command->info('✅ ارزهای پیش‌فرض با موفقیت ایجاد شد.');
    }
}
