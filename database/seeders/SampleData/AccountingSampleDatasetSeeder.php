<?php

namespace RMS\Accounting\Database\Seeders\SampleData;

use Illuminate\Database\Seeder;
use RMS\Accounting\Services\AccountingSampleDataService;
use RuntimeException;

class AccountingSampleDatasetSeeder extends Seeder
{
    public function run(): void
    {
        /** @var AccountingSampleDataService $service */
        $service = app(AccountingSampleDataService::class);

        try {
            $summary = $service->runFreshRebuild(function (string $line): void {
                if ($this->command) {
                    $this->command->line($line);
                }
            });
        } catch (RuntimeException $e) {
            if ($this->command) {
                $this->command->error($e->getMessage());
            }
            throw $e;
        }

        if ($this->command) {
            $this->command->info((string) trans('accounting::accounting.sample_data.logs.summary'));
            foreach ($summary as $key => $count) {
                $this->command->line(' - '.$key.': '.$count);
            }
        }
    }
}

