<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

/**
 * شبیه‌ساز تامین‌کنندگان
 */
class SuppliersSimulator extends BaseSimulator
{
    protected $faker;

    public function simulate(): void
    {
        $this->faker = Faker::create('fa_IR');
        $count = $this->option('suppliers', 300);

        $this->info("  🏭 در حال ایجاد {$count} تامین‌کننده...");

        $bar = $this->createProgressBar($count);
        $bar->start();

        $domesticCount = (int) ($count * 0.70); // 70% داخلی
        $foreignCount = $count - $domesticCount; // 30% خارجی

        $suppliers = [];
        $id = 1;

        // Domestic Suppliers
        for ($i = 0; $i < $domesticCount; $i++) {
            $suppliers[] = $this->generateSupplier($id++, 'domestic');
            $bar->advance();
        }

        // Foreign Suppliers
        for ($i = 0; $i < $foreignCount; $i++) {
            $suppliers[] = $this->generateSupplier($id++, 'foreign');
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        $this->bulkInsert('suppliers', $suppliers, 500);

        $this->success("تامین‌کنندگان: {$count} تامین‌کننده ایجاد شد (داخلی: {$domesticCount}, خارجی: {$foreignCount})");
    }

    protected function generateSupplier(int $id, string $type): array
    {
        $categories = ['مواد اولیه', 'قطعات', 'محصولات نهایی', 'بسته‌بندی'];
        
        return [
            'id' => $id,
            'code' => 'SUP' . str_pad($id, 6, '0', STR_PAD_LEFT),
            'name' => $type === 'domestic' 
                ? 'شرکت ' . $this->faker->company 
                : $this->faker->company . ' Co.',
            'contact_person' => $this->faker->name,
            'phone' => $this->faker->phoneNumber,
            'email' => $type === 'domestic' ? $this->faker->email : 'info@' . strtolower($this->faker->word) . '.com',
            'address' => $type === 'domestic' ? $this->faker->address : $this->faker->country,
            'currency_code' => $type === 'domestic' ? 'IRR' : (rand(0, 1) ? 'CNY' : 'USD'),
            'payment_terms_days' => rand(30, 90),
            'category' => $categories[array_rand($categories)],
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
