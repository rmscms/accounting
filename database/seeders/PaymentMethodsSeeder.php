<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Models\PaymentMethod;

/**
 * Seeder روش‌های پرداخت پیش‌فرض
 */
class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'cash',
                'name' => 'نقدی',
                'type' => 'cash',
                'requires_bank' => false,
                'requires_pos' => false,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'pos',
                'name' => 'کارتخوان (POS)',
                'type' => 'pos',
                'requires_bank' => false,
                'requires_pos' => true,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'online',
                'name' => 'پرداخت آنلاین',
                'type' => 'online',
                'requires_bank' => true,
                'requires_pos' => false,
                'requires_gateway' => true,
                'active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'cheque',
                'name' => 'چک',
                'type' => 'cheque',
                'requires_bank' => true,
                'requires_pos' => false,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'card_transfer',
                'name' => 'کارت به کارت',
                'type' => 'card_transfer',
                'requires_bank' => true,
                'requires_pos' => false,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'bank_transfer',
                'name' => 'انتقال بین بانکی (پایا/ساتنا)',
                'type' => 'bank_transfer',
                'requires_bank' => true,
                'requires_pos' => false,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 6,
            ],
            [
                'code' => 'wallet',
                'name' => 'کیف پول',
                'type' => 'wallet',
                'requires_bank' => false,
                'requires_pos' => false,
                'requires_gateway' => false,
                'active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($methods as $method) {
            // اگر روش پرداخت با این کد وجود داشت، skip کن
            $existing = PaymentMethod::where('code', $method['code'])->first();
            if (!$existing) {
                PaymentMethod::create($method);
            }
        }

        $this->command->info('✅ روش‌های پرداخت پیش‌فرض با موفقیت ایجاد شد.');
    }
}
