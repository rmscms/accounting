<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Models\TaxRate;

/**
 * Seeder نرخ‌های مالیات پیش‌فرض
 */
class TaxRatesSeeder extends Seeder
{
    public function run(): void
    {
        $taxRates = [
            [
                'name' => 'مالیات بر ارزش افزوده (VAT)',
                'rate' => 9.00,
                'type' => 'vat',
                'is_default' => true,
                'active' => true,
            ],
            [
                'name' => 'معاف از مالیات',
                'rate' => 0.00,
                'type' => 'exempt',
                'is_default' => false,
                'active' => true,
            ],
        ];

        foreach ($taxRates as $taxRate) {
            TaxRate::create($taxRate);
        }

        $this->command->info('✅ نرخ‌های مالیات پیش‌فرض با موفقیت ایجاد شد.');
    }
}
