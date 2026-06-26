<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    | ارز پیش‌فرض سیستم (معمولاً IRR)
    */
    'default_currency' => env('ACCOUNTING_DEFAULT_CURRENCY', 'IRR'),

    /*
    |--------------------------------------------------------------------------
    | Admin date UI (فیلتر و فیلدهای تاریخ در ادمین حسابداری)
    |--------------------------------------------------------------------------
    | ui: jalali | gregorian | auto — در auto با locale فارسی جلالی وگرنه میلادی.
    | ورودی کاربر همیشه به میلادی برای کوئری/DB نرمال می‌شود.
    */
    'date_filter' => [
        'ui' => env('ACCOUNTING_DATE_UI', 'jalali'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year
    |--------------------------------------------------------------------------
    | تنظیمات سال مالی
    */
    'default_fiscal_year_start' => '01-01', // فروردین
    'default_fiscal_year_end' => '12-29', // اسفند
    
    /*
    |--------------------------------------------------------------------------
    | Multi-Currency
    |--------------------------------------------------------------------------
    | فعال‌سازی پشتیبانی از ارزهای چندگانه
    */
    'enable_multi_currency' => env('ACCOUNTING_MULTI_CURRENCY', true),
    
    /*
    |--------------------------------------------------------------------------
    | VAT (Value Added Tax)
    |--------------------------------------------------------------------------
    | تنظیمات مالیات بر ارزش افزوده
    */
    'enable_vat' => env('ACCOUNTING_ENABLE_VAT', true),
    'default_vat_rate' => env('ACCOUNTING_DEFAULT_VAT_RATE', 9), // درصد
    
    /*
    |--------------------------------------------------------------------------
    | Ledger Immutability
    |--------------------------------------------------------------------------
    | آیا Ledger غیرقابل تغییر است؟ (توصیه می‌شود true باشد)
    */
    'ledger_immutable' => env('ACCOUNTING_LEDGER_IMMUTABLE', true),
    
    /*
    |--------------------------------------------------------------------------
    | Cost Method
    |--------------------------------------------------------------------------
    | روش محاسبه بهای تمام شده: FIFO, LIFO, AVG
    */
    'cost_method' => env('ACCOUNTING_COST_METHOD', 'FIFO'),
    
    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    | آیا تطبیق پرداخت‌ها الزامی است؟
    */
    'reconciliation_required' => env('ACCOUNTING_RECONCILIATION_REQUIRED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Account Code Format
    |--------------------------------------------------------------------------
    | فرمت کد حساب‌ها
    */
    'account_code_format' => [
        'general' => '{level}-{code}', // مثال: 1-100
        'separator' => '-',
        'levels' => [
            1 => 'general',     // کل
            2 => 'subsidiary',  // معین
            3 => 'analytical',  // تفصیلی
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Document Numbering
    |--------------------------------------------------------------------------
    | فرمت شماره‌گذاری اسناد
    */
    'document_numbering' => [
        'prefix' => env('ACCOUNTING_DOC_PREFIX', 'ACC'),
        'format' => '{prefix}-{year}-{number}', // ACC-2025-00001
        'padding' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    */
    'invoice_numbering' => [
        'prefix' => env('ACCOUNTING_INV_PREFIX', 'INV'),
        'format' => '{prefix}-{year}-{number}',
        'padding' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Payment Numbering
    |--------------------------------------------------------------------------
    */
    'payment_numbering' => [
        'prefix' => env('ACCOUNTING_PAY_PREFIX', 'PAY'),
        'format' => '{prefix}-{year}-{number}',
        'padding' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | System Accounts
    |--------------------------------------------------------------------------
    | حساب‌های سیستمی پیش‌فرض
    */
    'system_accounts' => [
        'assets' => [
            'cash' => '1-101',
            'bank' => '1-102',
            'accounts_receivable' => '1-120',
            'inventory' => '1-130',
            /** مطالبات وام کارکنان */
            'employee_loans_receivable' => '1305',
            /** چک‌های دریافتی در جریان وصول — بدهکار هنگام دریافت فیزیک چک (مرحلهٔ اول) */
            'cheques_receivable_clearing' => '1-125',
        ],
        'liabilities' => [
            'accounts_payable' => '2-210',
            'vat_payable' => '2-220',
            /** چک‌های پرداختنی — بستانکار هنگام صدور چک پرداختی (مرحلهٔ اول) */
            'cheques_payable_clearing' => '2-215',
            /** معین حقوق پرداختنی (زیر 2100 در چارت ۴ رقمی AccountsSeeder) */
            'wages_payable' => '2103',
            /** بدهی به سازمان تأمین تا زمان واریز */
            'social_insurance_payable' => '2104',
            /** پرداختنی سهم بیمهٔ کارمند (در صورت تفکیک از 2104) */
            'employee_insurance_payable' => '2105',
            /** پرداختنی سهم بیمهٔ کارفرما (در صورت تفکیک از 2104) */
            'employer_insurance_payable' => '2106',
            /** مالیات حقوق پرداختنی */
            'payroll_tax_payable' => '2107',
            /** سایر کسورات حقوق پرداختنی */
            'other_payroll_deductions_payable' => '2108',
            /** ذخیره مزایای پایان خدمت (سنوات) */
            'payroll_seniority_reserve' => '2109',
        ],
        'equity' => [
            'capital' => '3-301',
            'retained_earnings' => '3-320',
            /** والد/معین برداشت سهامداران — هم‌تراز با AccountsSeeder 3300 و Simulator 3-3 */
            'shareholder_drawings' => '3300',
        ],
        'revenue' => [
            'sales' => '4-401',
            'sales_returns' => '4-402',
            /** درآمد بهرهٔ وام کارکنان */
            'employee_loan_interest_income' => '4105',
            /** درآمد سود بانکی (در تطبیق بانک) */
            'bank_interest_income' => '4105',
        ],
        'expenses' => [
            'cogs' => '5-501',
            'operating_expenses' => '5-520',
            'fx_loss' => '5-530',
            /** هزینه سنوات حقوق */
            'payroll_seniority' => '5211',
            /** هزینهٔ سهم کارفرما در بیمه — 5210 در Seeder، 5-2-12 در Simulator */
            'employer_social_insurance' => '5210',
            /** هزینه کارمزد بانکی (در تطبیق بانک) */
            'bank_charges' => '5-520',
        ],
        'gains' => [
            'fx_gain' => '6-601',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Report Settings
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'date_format' => 'Y-m-d',
        'number_format' => [
            'decimals' => 0,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'export_formats' => ['pdf', 'excel', 'csv'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Expense categories (دسته‌بندی هزینه — فرم اختصاصی)
    |--------------------------------------------------------------------------
    */
    'expense_categories' => [
        'code_prefix' => env('ACCOUNTING_EXPENSE_CAT_CODE_PREFIX', 'EC'),
        'code_separator' => env('ACCOUNTING_EXPENSE_CAT_CODE_SEPARATOR', '-'),
        'enforce_uppercase' => env('ACCOUNTING_EXPENSE_CAT_UPPERCASE', true),
        'code_min_length' => (int) env('ACCOUNTING_EXPENSE_CAT_CODE_MIN', 2),
        'code_max_length' => (int) env('ACCOUNTING_EXPENSE_CAT_CODE_MAX', 50),
        /** الگوی نهایی پس از نرمال‌سازی (بدون محدودیت پیشوند اگر خالی باشد) */
        'code_pattern' => env('ACCOUNTING_EXPENSE_CAT_CODE_PATTERN', '/^[A-Z0-9_-]+$/'),
        'auto_suggest_next' => env('ACCOUNTING_EXPENSE_CAT_AUTO_SUGGEST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | فرم اختصاصی هزینه (دسته‌های پیشنهادی / پرکاربرد)
    |--------------------------------------------------------------------------
    | در صورت وجود config/expense_ui.php در اپ، همان اولویت دارد.
    */
    'expense_ui' => [
        'featured_category_ids' => [],
        'usage_lookback_days' => 90,
        'usage_top_n' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | پیوست‌های فایلی خصوصی (رسید هزینه و …) — نه سند دفترکل
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'disk' => env('ACCOUNTING_ATTACHMENTS_DISK', 'local'),
        'directory' => env('ACCOUNTING_ATTACHMENTS_DIR', 'accounting/private'),
        'max_size_kb' => (int) env('ACCOUNTING_ATTACHMENTS_MAX_KB', 10240),
        'max_per_expense' => (int) env('ACCOUNTING_ATTACHMENTS_MAX_PER_EXPENSE', 5),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | فرم بانک — حساب مرتبط (شاخه‌های اصلی + جستجوی زیرحساب)
    |--------------------------------------------------------------------------
    | در کشوی حساب: بدون تایپ، فقط «معین»ها (سطح پیش‌فرض ۲) لود می‌شود؛
    | با حداقل ۲ کاراکتر جستجو، تفصیلی‌ها (مثلاً زیر مطالبات) هم برمی‌گردد.
    | optional: initial_ledger_branch_codes برای محدود کردن به چند کد مشخص
    */
    'banks' => [
        'initial_ledger_branch_level' => (int) env('ACCOUNTING_BANK_INITIAL_BRANCH_LEVEL', 2),
        'initial_ledger_branch_max' => (int) env('ACCOUNTING_BANK_INITIAL_BRANCH_MAX', 40),
        'initial_ledger_branch_codes' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Treasury Sub-Accounts (auto-provision)
    |--------------------------------------------------------------------------
    | Parent ledger code for automatic bank/cashbox sub-account creation.
    */
    'treasury_sub_accounts' => [
        'bank_parent_code' => env('ACCOUNTING_TREASURY_BANK_PARENT_CODE', '1102'),
        'cashbox_parent_code' => env('ACCOUNTING_TREASURY_CASHBOX_PARENT_CODE', '1101'),
    ],

    /*
    |--------------------------------------------------------------------------
    | نصب اولیه / ویزارد (سید + نگاشت settings)
    |--------------------------------------------------------------------------
    | require_wizard: اگر true باشد تا تکمیل ویزارد، داشبورد به install ریدایرکت می‌شود.
    */
    'install' => [
        'require_wizard' => env('ACCOUNTING_INSTALL_REQUIRE_WIZARD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll chart (per-employee provisioning)
    |--------------------------------------------------------------------------
    | والد هزینهٔ حقوق در چارت ۴ رقمی پیش‌فرض پکیج.
    */
    'payroll' => [
        'wages_expense_parent_code' => env('ACCOUNTING_PAYROLL_WAGES_EXPENSE_PARENT_CODE', '5201'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'admin' => [
            'enabled' => env('ACCOUNTING_ADMIN_ROUTES', true),
            'prefix' => 'admin/accounting',
            'middleware' => ['web', 'auth:sanctum'],
        ],
        'admin_prefix' => 'admin/accounting',
        'admin_middleware' => ['web', 'auth:admin'],
        /** میدلور اضافه روی کل گروه ادمین حسابداری (مثلاً normalize.persian.dates) */
        'admin_extra_middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | حساب‌های دفترکل (شناسه رکورد accounts) — اختیاری؛ در صورت خالی بودن از کدهای system_accounts حدس زده می‌شود
    |--------------------------------------------------------------------------
    */
    'accounts' => [
        'cheques_receivable_clearing' => env('ACCOUNTING_ACC_CHEQUE_AR_CLEARING_ID'),
        'cheques_payable_clearing' => env('ACCOUNTING_ACC_CHEQUE_AP_CLEARING_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | داشبورد: دکمهٔ «ایجاد حساب و نوشتن .env» برای چک‌های انتظامی
    |--------------------------------------------------------------------------
    */
    'allow_dashboard_cheque_clearing_setup' => env('ACCOUNTING_ALLOW_DASHBOARD_CHEQUE_SETUP', true),

    /*
    |--------------------------------------------------------------------------
    | خرید — برگشت، یادداشت بدهکار، سفارش خرید
    |--------------------------------------------------------------------------
    | debit_note_prefill_from_invoice: هنگام ایجاد یادداشت بدهکار از UI، در صورت نوع «برگشت» و
    | وجود فاکتور مرجع، اقلام فاکتور به‌صورت پیش‌فرض روی debit_note_items کپی می‌شوند (با ?prefill_invoice_items=0 غیرفعال).
    | debit_note_issue_inventory_adjustment: پس از صدور یادداشت بدهکار نوع برگشت، سند تعدیل موجودی
    | در وضعیت «تأیید شده» (بدون ثبت سند دفترکل تعدیل) برای ردیابی کاهش موجودی ایجاد می‌شود.
    | return_po_policy: link_only = سفارش خرید به‌صورت خودکار از روی برگشت به‌روز نمی‌شود؛ فقط گزارش/لینک.
    */
    'purchases' => [
        'debit_note_prefill_from_invoice' => env('ACCOUNTING_DN_PREFILL_INVOICE_ITEMS', true),
        'debit_note_issue_inventory_adjustment' => env('ACCOUNTING_DN_ISSUE_INVENTORY_ADJUSTMENT', true),
        'debit_note_inventory_warehouse_id' => env('ACCOUNTING_DN_INVENTORY_WAREHOUSE_ID'),
        'return_po_policy' => env('ACCOUNTING_RETURN_PO_POLICY', 'link_only'),
    ],
];
