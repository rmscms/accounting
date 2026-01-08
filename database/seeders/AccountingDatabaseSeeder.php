<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder اصلی پکیج Accounting
 */
class AccountingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountsSeeder::class,
            CurrenciesSeeder::class,
            PaymentMethodsSeeder::class,
            TaxRatesSeeder::class,
            FiscalYearsSeeder::class,
        ]);

        $this->command->info('✅ همه Seeders پکیج Accounting با موفقیت اجرا شد.');
    }
}
