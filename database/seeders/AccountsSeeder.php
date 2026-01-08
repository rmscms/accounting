<?php

namespace RMS\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Accounting\Models\Account;

/**
 * Seeder دسته حساب‌های پیش‌فرض
 * بر اساس استاندارد حسابداری ایران
 */
class AccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // دارایی‌ها (Assets) - 1xxx
            [
                'code' => '1000',
                'name' => 'دارایی‌ها',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '1100',
                'name' => 'دارایی‌های جاری',
                'level' => 2,
                'parent_id' => 1, // دارایی‌ها
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '1101',
                'name' => 'صندوق',
                'level' => 3,
                'parent_id' => 2, // دارایی‌های جاری
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '1102',
                'name' => 'بانک',
                'level' => 3,
                'parent_id' => 2,
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '1103',
                'name' => 'حساب‌های دریافتنی',
                'level' => 3,
                'parent_id' => 2,
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '1104',
                'name' => 'موجودی کالا',
                'level' => 3,
                'parent_id' => 2,
                'account_type' => 'asset',
                'is_system' => true,
                'active' => true,
            ],

            // بدهی‌ها (Liabilities) - 2xxx
            [
                'code' => '2000',
                'name' => 'بدهی‌ها',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'liability',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '2100',
                'name' => 'بدهی‌های جاری',
                'level' => 2,
                'parent_id' => 7,
                'account_type' => 'liability',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '2101',
                'name' => 'حساب‌های پرداختنی',
                'level' => 3,
                'parent_id' => 8,
                'account_type' => 'liability',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '2102',
                'name' => 'مالیات بر ارزش افزوده پرداختنی',
                'level' => 3,
                'parent_id' => 8,
                'account_type' => 'liability',
                'is_system' => true,
                'active' => true,
            ],

            // سرمایه (Equity) - 3xxx
            [
                'code' => '3000',
                'name' => 'حقوق صاحبان سهام',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'equity',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '3100',
                'name' => 'سرمایه',
                'level' => 2,
                'parent_id' => 11,
                'account_type' => 'equity',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '3200',
                'name' => 'سود (زیان) انباشته',
                'level' => 2,
                'parent_id' => 11,
                'account_type' => 'equity',
                'is_system' => true,
                'active' => true,
            ],

            // درآمدها (Revenue) - 4xxx
            [
                'code' => '4000',
                'name' => 'درآمدها',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'revenue',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '4100',
                'name' => 'درآمد فروش',
                'level' => 2,
                'parent_id' => 14,
                'account_type' => 'revenue',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '4200',
                'name' => 'سایر درآمدها',
                'level' => 2,
                'parent_id' => 14,
                'account_type' => 'revenue',
                'is_system' => true,
                'active' => true,
            ],

            // هزینه‌ها (Expenses) - 5xxx
            [
                'code' => '5000',
                'name' => 'هزینه‌ها',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '5100',
                'name' => 'بهای تمام شده کالای فروش رفته',
                'level' => 2,
                'parent_id' => 17,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '5200',
                'name' => 'هزینه‌های عملیاتی',
                'level' => 2,
                'parent_id' => 17,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '5201',
                'name' => 'هزینه حقوق و دستمزد',
                'level' => 3,
                'parent_id' => 19,
                'account_type' => 'expense',
                'is_system' => false,
                'active' => true,
            ],
            [
                'code' => '5202',
                'name' => 'هزینه اجاره',
                'level' => 3,
                'parent_id' => 19,
                'account_type' => 'expense',
                'is_system' => false,
                'active' => true,
            ],
            [
                'code' => '5203',
                'name' => 'هزینه برق و آب',
                'level' => 3,
                'parent_id' => 19,
                'account_type' => 'expense',
                'is_system' => false,
                'active' => true,
            ],
            [
                'code' => '5204',
                'name' => 'هزینه تبلیغات',
                'level' => 3,
                'parent_id' => 19,
                'account_type' => 'expense',
                'is_system' => false,
                'active' => true,
            ],
            [
                'code' => '5300',
                'name' => 'سود و زیان نرخ ارز',
                'level' => 2,
                'parent_id' => 17,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '5301',
                'name' => 'سود نرخ ارز',
                'level' => 3,
                'parent_id' => 24,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '5302',
                'name' => 'زیان نرخ ارز',
                'level' => 3,
                'parent_id' => 24,
                'account_type' => 'expense',
                'is_system' => true,
                'active' => true,
            ],
        ];

        foreach ($accounts as $accountData) {
            Account::create($accountData);
        }

        $this->command->info('✅ حساب‌های پیش‌فرض با موفقیت ایجاد شد.');
    }
}
