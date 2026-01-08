<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

/**
 * شبیه‌ساز مشتریان
 */
class CustomersSimulator extends BaseSimulator
{
    protected $faker;

    public function simulate(): void
    {
        $this->faker = Faker::create('fa_IR');
        $count = $this->option('customers', 2000);

        $this->info("  👥 در حال ایجاد {$count} مشتری...");

        $bar = $this->createProgressBar($count);
        $bar->start();

        // دسته‌بندی مشتریان
        $vipCount = (int) ($count * 0.10); // 10% VIP
        $regularCount = (int) ($count * 0.60); // 60% Regular
        $occasionalCount = $count - $vipCount - $regularCount; // 30% Occasional

        $customers = [];
        $id = 1;

        // VIP Customers
        for ($i = 0; $i < $vipCount; $i++) {
            $customers[] = $this->generateCustomer($id++, 'VIP');
            $bar->advance();
        }

        // Regular Customers
        for ($i = 0; $i < $regularCount; $i++) {
            $customers[] = $this->generateCustomer($id++, 'Regular');
            $bar->advance();
        }

        // Occasional Customers
        for ($i = 0; $i < $occasionalCount; $i++) {
            $customers[] = $this->generateCustomer($id++, 'Occasional');
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        // Bulk insert
        $this->bulkInsert('customers', $customers, 500);

        $this->success("مشتریان: {$count} مشتری ایجاد شد (VIP: {$vipCount}, Regular: {$regularCount}, Occasional: {$occasionalCount})");
    }

    protected function generateCustomer(int $id, string $type): array
    {
        $creditLimits = [
            'VIP' => [500000000, 2000000000], // 500M-2B
            'Regular' => [100000000, 500000000], // 100M-500M
            'Occasional' => [10000000, 100000000], // 10M-100M
        ];

        return [
            'id' => $id,
            'name' => $this->faker->name,
            'type' => $type,
            'national_code' => $this->faker->numerify('##########'),
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'credit_limit' => rand(...$creditLimits[$type]),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
