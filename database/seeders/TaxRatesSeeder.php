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
                'code' => 'VAT-9',
                'name' => 'مالیات بر ارزش افزوده (VAT)',
                'rate' => 9.00,
                'tax_type' => 'vat',
                'is_default' => true,
                'active' => true,
            ],
            [
                'code' => 'EXEMPT',
                'name' => 'معاف از مالیات',
                'rate' => 0.00,
                'tax_type' => 'other',
                'is_default' => false,
                'active' => true,
            ],
        ];

        foreach ($taxRates as $taxRate) {
            // اگر نرخ مالیات با این کد وجود داشت، skip کن
            $existing = TaxRate::where('code', $taxRate['code'])->first();
            if (!$existing) {
                TaxRate::create($taxRate);
            }
        }

        $this->command->info('✅ نرخ‌های مالیات پیش‌فرض بررسی و ایجاد شدند.');
    }
}
