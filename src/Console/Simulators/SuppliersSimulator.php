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
        $count = $this->option('suppliers', 300);
        
        // چک کردن تعداد تامین‌کنندگان موجود
        $existingCount = DB::table('suppliers')->count();
        
        if ($existingCount >= $count) {
            $this->success("تامین‌کنندگان: {$existingCount} تامین‌کننده موجود (skip)");
            return;
        }
        
        $this->faker = Faker::create('fa_IR');
        $toCreate = $count - $existingCount;

        $this->info("  🏭 در حال ایجاد {$toCreate} تامین‌کننده جدید...");

        $bar = $this->createProgressBar($toCreate);
        $bar->start();

        $domesticCount = (int) ($toCreate * 0.70); // 70% داخلی
        $foreignCount = $toCreate - $domesticCount; // 30% خارجی

        $suppliers = [];
        $id = $existingCount + 1;

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
        // Get accounts payable account ID
        $accountsPayableAccount = DB::table('accounts')
            ->where('code', '2-1-1')
            ->first();
        
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
            'account_id' => $accountsPayableAccount->id,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
