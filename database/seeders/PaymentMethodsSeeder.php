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
                'name_en' => 'Cash',
                'type' => 'cash',
                'requires_bank' => false,
                'requires_reference' => false,
                'active' => true,
            ],
            [
                'code' => 'pos',
                'name' => 'کارتخوان (POS)',
                'name_en' => 'POS Terminal',
                'type' => 'pos',
                'requires_bank' => false,
                'requires_reference' => true,
                'active' => true,
            ],
            [
                'code' => 'online',
                'name' => 'پرداخت آنلاین',
                'name_en' => 'Online Payment',
                'type' => 'online',
                'requires_bank' => true,
                'requires_reference' => true,
                'active' => true,
            ],
            [
                'code' => 'cheque',
                'name' => 'چک',
                'name_en' => 'Cheque',
                'type' => 'cheque',
                'requires_bank' => true,
                'requires_reference' => true,
                'active' => true,
            ],
            [
                'code' => 'card_to_card',
                'name' => 'کارت به کارت',
                'name_en' => 'Card to Card',
                'type' => 'card_to_card',
                'requires_bank' => true,
                'requires_reference' => true,
                'active' => true,
            ],
            [
                'code' => 'wallet',
                'name' => 'کیف پول',
                'name_en' => 'Wallet',
                'type' => 'wallet',
                'requires_bank' => false,
                'requires_reference' => false,
                'active' => true,
            ],
            [
                'code' => 'bank_transfer',
                'name' => 'انتقال بانکی',
                'name_en' => 'Bank Transfer',
                'type' => 'bank',
                'requires_bank' => true,
                'requires_reference' => true,
                'active' => true,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::create($method);
        }

        $this->command->info('✅ روش‌های پرداخت پیش‌فرض با موفقیت ایجاد شد.');
    }
}
