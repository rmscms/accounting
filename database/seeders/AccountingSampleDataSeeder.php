<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Database\Seeders\SampleData\AccountingSampleDatasetSeeder;

class AccountingSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountingSampleDatasetSeeder::class,
        ]);
    }
}

