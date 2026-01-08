<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Models\FiscalYear;

/**
 * Seeder سال مالی پیش‌فرض
 */
class FiscalYearsSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = now()->year;

        $fiscalYears = [
            [
                'year_code' => (string) $currentYear,
                'start_date' => "{$currentYear}-01-01",
                'end_date' => "{$currentYear}-12-31",
                'status' => 'open',
                'is_current' => true,
            ],
        ];

        foreach ($fiscalYears as $fiscalYear) {
            FiscalYear::create($fiscalYear);
        }

        $this->command->info("✅ سال مالی {$currentYear} با موفقیت ایجاد شد.");
    }
}
