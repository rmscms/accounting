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
                'code' => 'IRT',
                'name' => 'تومان ایران',
                'symbol' => 'تومان',
                'is_base' => true,
                'active' => true,
            ],
            [
                'code' => 'IRR',
                'name' => 'ریال ایران',
                'symbol' => 'ریال',
                'is_base' => false,
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
            [
                'code' => 'CNY',
                'name' => 'یوان چین',
                'symbol' => '¥',
                'is_base' => false,
                'active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'is_base' => (bool) $currency['is_base'],
                    'active' => (bool) $currency['active'],
                ]
            );
        }

        // در راه‌اندازی اولیه، فقط IRT باید ارز پایه باشد.
        Currency::query()->where('code', '!=', 'IRT')->update(['is_base' => false]);
        Currency::query()->where('code', 'IRT')->update(['is_base' => true, 'active' => true]);

        $this->command->info('✅ ارزهای پیش‌فرض بررسی و ایجاد شدند.');
    }
}
