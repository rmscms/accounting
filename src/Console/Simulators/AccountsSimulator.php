<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

/**
 * شبیه‌ساز Chart of Accounts
 */
class AccountsSimulator extends BaseSimulator
{
    public function simulate(): void
    {
        $this->info('  📊 در حال ایجاد Chart of Accounts...');

        $accounts = $this->getChartOfAccounts();
        $idMap = []; // Map code => id
        
        // First, load existing accounts
        $existingAccounts = DB::table('accounts')->get()->keyBy('code');
        
        foreach ($accounts as $account) {
            // Skip if account already exists
            if ($existingAccounts->has($account['code'])) {
                $idMap[$account['code']] = $existingAccounts[$account['code']]->id;
                continue;
            }
            
            // Resolve parent_id from parent_code
            if (isset($account['parent_code']) && $account['parent_code'] !== null) {
                $account['parent_id'] = $idMap[$account['parent_code']] ?? null;
            } else {
                $account['parent_id'] = null;
            }
            unset($account['parent_code']);
            
            $id = DB::table('accounts')->insertGetId(array_merge($account, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            
            // Store ID for future references
            $idMap[$account['code']] = $id;
        }

        $newAccounts = count($accounts) - $existingAccounts->count();
        if ($newAccounts > 0) {
            $this->success("حساب‌ها: {$newAccounts} حساب جدید ایجاد شد");
        } else {
            $this->success('حساب‌ها: از قبل موجود بودند');
        }
    }

    /**
     * لیست کامل حساب‌ها
     */
    protected function getChartOfAccounts(): array
    {
        return [
            // دارایی‌ها (Assets) - 1
            ['code' => '1', 'name' => 'دارایی‌ها', 'level' => 1, 'parent_code' => null, 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1', 'name' => 'دارایی‌های جاری', 'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-1', 'name' => 'صندوق', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-2', 'name' => 'بانک', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-3', 'name' => 'حساب‌های دریافتنی', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-4', 'name' => 'چک دریافتنی', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-5', 'name' => 'موجودی کالا', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-1-6', 'name' => 'مالیات بر ارزش افزوده دریافتنی', 'level' => 3, 'parent_code' => '1-1', 'account_type' => 'asset', 'is_system' => true, 'active' => true],
            ['code' => '1-2', 'name' => 'دارایی‌های ثابت', 'level' => 2, 'parent_code' => '1', 'account_type' => 'asset', 'is_system' => false, 'active' => true],
            ['code' => '1-2-1', 'name' => 'ساختمان', 'level' => 3, 'parent_code' => '1-2', 'account_type' => 'asset', 'is_system' => false, 'active' => true],
            ['code' => '1-2-2', 'name' => 'ماشین‌آلات', 'level' => 3, 'parent_code' => '1-2', 'account_type' => 'asset', 'is_system' => false, 'active' => true],

            // بدهی‌ها (Liabilities) - 2
            ['code' => '2', 'name' => 'بدهی‌ها', 'level' => 1, 'parent_code' => null, 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1', 'name' => 'بدهی‌های جاری', 'level' => 2, 'parent_code' => '2', 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1-1', 'name' => 'حساب‌های پرداختنی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1-2', 'name' => 'چک پرداختنی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1-3', 'name' => 'مالیات پرداختنی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1-4', 'name' => 'حقوق پرداختنی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => false, 'active' => true],
            ['code' => '2-1-5', 'name' => 'مالیات بر ارزش افزوده پرداختنی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => true, 'active' => true],
            ['code' => '2-1-6', 'name' => 'پرداختنی سازمان تأمین اجتماعی', 'level' => 3, 'parent_code' => '2-1', 'account_type' => 'liability', 'is_system' => true, 'active' => true],

            // سرمایه (Equity) - 3
            ['code' => '3', 'name' => 'سرمایه', 'level' => 1, 'parent_code' => null, 'account_type' => 'equity', 'is_system' => true, 'active' => true],
            ['code' => '3-1', 'name' => 'سرمایه اولیه', 'level' => 2, 'parent_code' => '3', 'account_type' => 'equity', 'is_system' => true, 'active' => true],
            ['code' => '3-2', 'name' => 'سود انباشته', 'level' => 2, 'parent_code' => '3', 'account_type' => 'equity', 'is_system' => true, 'active' => true],
            ['code' => '3-3', 'name' => 'برداشت صاحبان سهام', 'level' => 2, 'parent_code' => '3', 'account_type' => 'equity', 'is_system' => true, 'active' => true],

            // درآمد (Revenue) - 4
            ['code' => '4', 'name' => 'درآمدها', 'level' => 1, 'parent_code' => null, 'account_type' => 'income', 'is_system' => true, 'active' => true],
            ['code' => '4-1', 'name' => 'فروش', 'level' => 2, 'parent_code' => '4', 'account_type' => 'income', 'is_system' => true, 'active' => true],
            ['code' => '4-1-1', 'name' => 'فروش کالا', 'level' => 3, 'parent_code' => '4-1', 'account_type' => 'income', 'is_system' => true, 'active' => true],
            ['code' => '4-2', 'name' => 'سود تسعیر ارز', 'level' => 2, 'parent_code' => '4', 'account_type' => 'income', 'is_system' => true, 'active' => true],
            ['code' => '4-3', 'name' => 'سایر درآمدها', 'level' => 2, 'parent_code' => '4', 'account_type' => 'income', 'is_system' => false, 'active' => true],

            // هزینه (Expense) - 5
            ['code' => '5', 'name' => 'هزینه‌ها', 'level' => 1, 'parent_code' => null, 'account_type' => 'expense', 'is_system' => true, 'active' => true],
            ['code' => '5-1', 'name' => 'بهای تمام شده کالای فروش رفته', 'level' => 2, 'parent_code' => '5', 'account_type' => 'expense', 'is_system' => true, 'active' => true],
            ['code' => '5-2', 'name' => 'هزینه‌های عملیاتی', 'level' => 2, 'parent_code' => '5', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-1', 'name' => 'حقوق و دستمزد', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-2', 'name' => 'اجاره', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-3', 'name' => 'برق', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-4', 'name' => 'آب', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-5', 'name' => 'گاز', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-6', 'name' => 'تلفن و اینترنت', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-7', 'name' => 'بیمه', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-8', 'name' => 'حمل و نقل', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-9', 'name' => 'تعمیرات', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-10', 'name' => 'تبلیغات', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-11', 'name' => 'پذیرایی', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-2-12', 'name' => 'حق بیمه سهم کارفرما', 'level' => 3, 'parent_code' => '5-2', 'account_type' => 'expense', 'is_system' => false, 'active' => true],
            ['code' => '5-3', 'name' => 'زیان تسعیر ارز', 'level' => 2, 'parent_code' => '5', 'account_type' => 'expense', 'is_system' => true, 'active' => true],
        ];
    }
}
