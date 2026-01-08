<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

/**
 * شبیه‌ساز خزانه‌داری
 */
class TreasurySimulator extends BaseSimulator
{
    public function simulate(): void
    {
        $this->info('  🏦 در حال ایجاد خزانه‌داری...');

        $this->createBanks();
        $this->createCashBoxes();
        $this->createPOSTerminals();
        $this->createPaymentMethods();

        $this->success('خزانه‌داری: 3 بانک، 2 صندوق، 2 POS، 5 روش پرداخت');
    }

    protected function createBanks(): void
    {
        $banks = [
            ['name' => 'بانک ملی', 'branch_name' => 'شعبه مرکزی', 'account_number' => '1001-' . rand(1000000, 9999999), 'currency_code' => 'IRR', 'balance' => 10000000000],
            ['name' => 'بانک ملت', 'branch_name' => 'شعبه تجاری', 'account_number' => '1002-' . rand(1000000, 9999999), 'currency_code' => 'CNY', 'balance' => 1000000],
            ['name' => 'بانک پاسارگاد', 'branch_name' => 'شعبه فروشگاهی', 'account_number' => '1003-' . rand(1000000, 9999999), 'currency_code' => 'IRR', 'balance' => 5000000000],
        ];

        foreach ($banks as $bank) {
            DB::table('banks')->insert(array_merge($bank, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function createCashBoxes(): void
    {
        $cashBoxes = [
            ['name' => 'صندوق اصلی فروشگاه', 'location' => 'فروشگاه طبقه اول', 'currency_code' => 'IRR', 'balance' => 500000000],
            ['name' => 'صندوق خرده فروشی', 'location' => 'فروشگاه طبقه دوم', 'currency_code' => 'IRR', 'balance' => 200000000],
        ];

        foreach ($cashBoxes as $cashBox) {
            DB::table('cash_boxes')->insert(array_merge($cashBox, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function createPOSTerminals(): void
    {
        $terminals = [
            ['name' => 'POS 1', 'serial_number' => 'POS' . rand(100000, 999999), 'terminal_id' => 'T' . rand(1000, 9999), 'bank_id' => 3, 'location' => 'صندوق اصلی'],
            ['name' => 'POS 2', 'serial_number' => 'POS' . rand(100000, 999999), 'terminal_id' => 'T' . rand(1000, 9999), 'bank_id' => 3, 'location' => 'صندوق خرده فروشی'],
        ];

        foreach ($terminals as $terminal) {
            DB::table('pos_terminals')->insert(array_merge($terminal, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function createPaymentMethods(): void
    {
        $methods = [
            ['code' => 'CASH', 'name' => 'نقدی', 'type' => 'cash', 'requires_bank' => false, 'requires_pos' => false, 'sort_order' => 1],
            ['code' => 'CARD_TO_CARD', 'name' => 'کارت به کارت', 'type' => 'transfer', 'requires_bank' => true, 'requires_pos' => false, 'sort_order' => 2],
            ['code' => 'POS', 'name' => 'کارتخوان', 'type' => 'pos', 'requires_bank' => false, 'requires_pos' => true, 'sort_order' => 3],
            ['code' => 'CHEQUE', 'name' => 'چک', 'type' => 'cheque', 'requires_bank' => true, 'requires_pos' => false, 'sort_order' => 4],
            ['code' => 'ONLINE', 'name' => 'پرداخت آنلاین', 'type' => 'online', 'requires_bank' => false, 'requires_pos' => false, 'requires_gateway' => true, 'sort_order' => 5],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->insert(array_merge($method, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
