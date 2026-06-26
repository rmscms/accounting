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

            // درآمدها (Income/Revenue) - 4xxx
            [
                'code' => '4000',
                'name' => 'درآمدها',
                'level' => 1,
                'parent_id' => null,
                'account_type' => 'income',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '4100',
                'name' => 'درآمد فروش',
                'level' => 2,
                'parent_id' => 14,
                'account_type' => 'income',
                'is_system' => true,
                'active' => true,
            ],
            [
                'code' => '4200',
                'name' => 'سایر درآمدها',
                'level' => 2,
                'parent_id' => 14,
                'account_type' => 'income',
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

        // نگاشت ایندکس ترتیبی قدیمی آرایه (1-based) به کد حساب برای حل parent_idهای عددی.
        $codeByLegacyIndex = [];
        foreach ($accounts as $idx => $row) {
            $codeByLegacyIndex[$idx + 1] = (string) $row['code'];
        }

        foreach ($accounts as $accountData) {
            $legacyParentId = isset($accountData['parent_id']) ? (int) $accountData['parent_id'] : 0;
            $parentCode = $legacyParentId > 0 ? ($codeByLegacyIndex[$legacyParentId] ?? null) : null;
            $parentId = null;
            if (is_string($parentCode) && $parentCode !== '') {
                $parentId = (int) (Account::query()->where('code', $parentCode)->value('id') ?? 0);
                if ($parentId < 1) {
                    // اگر والد هنوز ساخته نشده باشد، این رکورد فعلاً رد می‌شود تا دور بعدی ساخته شود.
                    continue;
                }
            }

            unset($accountData['parent_id']);
            $accountData['parent_id'] = $parentId;

            Account::query()->updateOrCreate(
                ['code' => $accountData['code']],
                $accountData
            );
        }

        /*
         * حساب‌های تکمیلی چارت (حقوق پرداختنی، برداشت سهامدار، بیمه) را اینجا اضافه کنید،
         * نه وسط آرایهٔ بالا — parent_idهای عددی آرایه به ترتیب درج وابسته‌اند.
         * معادل سلسله‌ای: AccountsSimulator::getChartOfAccounts()
         */
        $this->ensurePostSeedAccounts();

        $this->command->info('✅ حساب‌های پیش‌فرض بررسی و ایجاد شدند.');
    }

    /**
     * ایجاد idempotent حساب‌های والد/معین پس از سید اصلی (parent_id از روی کد والد).
     */
    protected function ensurePostSeedAccounts(): void
    {
        $defs = [
            ['code' => '2103', 'name' => 'حقوق پرداختنی', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2104', 'name' => 'پرداختنی سازمان تأمین اجتماعی', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2105', 'name' => 'پرداختنی بیمه سهم کارمند', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2106', 'name' => 'پرداختنی بیمه سهم کارفرما', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2107', 'name' => 'پرداختنی مالیات حقوق', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2108', 'name' => 'پرداختنی سایر کسورات حقوق', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '2109', 'name' => 'ذخیره مزایای پایان خدمت کارکنان', 'level' => 3, 'parent_code' => '2100', 'account_type' => 'liability', 'is_system' => true],
            ['code' => '3300', 'name' => 'برداشت صاحبان سهام', 'level' => 2, 'parent_code' => '3000', 'account_type' => 'equity', 'is_system' => true],
            ['code' => '3200', 'name' => 'سود (زیان) انباشته', 'level' => 2, 'parent_code' => '3000', 'account_type' => 'equity', 'is_system' => true],
            ['code' => '3900', 'name' => 'خلاصه سود و زیان', 'level' => 2, 'parent_code' => '3000', 'account_type' => 'equity', 'is_system' => true],
            ['code' => '5210', 'name' => 'حق بیمه سهم کارفرما', 'level' => 3, 'parent_code' => '5200', 'account_type' => 'expense', 'is_system' => false],
            ['code' => '5211', 'name' => 'هزینه سنوات حقوق', 'level' => 3, 'parent_code' => '5200', 'account_type' => 'expense', 'is_system' => true],
            ['code' => '5205', 'name' => 'هزینه استهلاکات', 'level' => 3, 'parent_code' => '5200', 'account_type' => 'expense', 'is_system' => true],
            ['code' => '5-520', 'name' => 'هزینه کارمزد بانکی', 'level' => 3, 'parent_code' => '5200', 'account_type' => 'expense', 'is_system' => true],
            ['code' => '1200', 'name' => 'دارایی‌های ثابت', 'level' => 2, 'parent_code' => '1000', 'account_type' => 'asset', 'is_system' => true],
            ['code' => '1201', 'name' => 'استهلاک انباشته دارایی‌های ثابت', 'level' => 3, 'parent_code' => '1200', 'account_type' => 'asset', 'is_system' => true],
            ['code' => '1305', 'name' => 'مطالبات وام کارکنان', 'level' => 3, 'parent_code' => '1100', 'account_type' => 'asset', 'is_system' => true],
            ['code' => '1105', 'name' => 'مالیات بر ارزش افزوده دریافتنی', 'level' => 3, 'parent_code' => '1100', 'account_type' => 'asset', 'is_system' => true],
            ['code' => '4105', 'name' => 'درآمد بهره وام کارکنان', 'level' => 3, 'parent_code' => '4100', 'account_type' => 'income', 'is_system' => true],
        ];

        foreach ($defs as $def) {
            if (Account::where('code', $def['code'])->exists()) {
                continue;
            }
            $parent = Account::where('code', $def['parent_code'])->first();
            if (! $parent) {
                continue;
            }
            Account::create([
                'code' => $def['code'],
                'name' => $def['name'],
                'level' => $def['level'],
                'parent_id' => $parent->id,
                'account_type' => $def['account_type'],
                'is_system' => $def['is_system'],
                'active' => true,
            ]);
        }
    }
}
