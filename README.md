# RMS Accounting Package

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E11.0%20%7C%20%5E12.0-red)](https://laravel.com)

**سیستم حسابداری کامل و حرفه‌ای برای Laravel با معماری Ledger-First و Double-Entry Accounting**

## ویژگی‌های کلیدی

### 🎯 هسته حسابداری
- ✅ **Ledger-First Design** - دفتر روزنامه به عنوان منبع حقیقت
- ✅ **Double-Entry Accounting** - ثبت دو طرفه تمام تراکنش‌ها
- ✅ **Immutable Ledger** - عدم امکان ویرایش/حذف (فقط INSERT)
- ✅ **Chart of Accounts** - سیستم درختی حساب‌ها (کل/معین/تفصیلی)
- ✅ **Accounting Documents** - سند حسابداری برای هر تراکنش

### 💰 مدیریت مالی
- ✅ **Multi-Currency Support** - پشتیبانی از ارزهای چندگانه
- ✅ **FX Rate Management** - مدیریت نرخ تبدیل ارز
- ✅ **Multi-Store** - پشتیبانی از چند فروشگاه
- ✅ **Fiscal Year Management** - مدیریت سال مالی

### 📊 دریافتنی و پرداختنی
- ✅ **Accounts Receivable** - مدیریت مطالبات مشتریان
- ✅ **Customer Invoices** - فاکتورهای فروش
- ✅ **Customer Payments** - دریافت‌های نقدی
- ✅ **Accounts Payable** - مدیریت بدهی به تامین‌کننده‌ها
- ✅ **Purchase Orders** - سفارش‌های خرید
- ✅ **Supplier Invoices** - فاکتورهای خرید
- ✅ **Supplier Payments** - پرداخت‌های انجام شده

### 🏦 خزانه‌داری
- ✅ **Bank Management** - مدیریت حساب‌های بانکی
- ✅ **Cash Box Management** - مدیریت صندوق‌های نقدی
- ✅ **POS Terminals** - مدیریت کارت‌خوان‌ها
- ✅ **Cheque Management** - مدیریت چک‌های دریافتی/پرداختی
- ✅ **Wallet System** - سیستم کیف پول
- ✅ **Payment Methods** - روش‌های پرداخت متنوع

### 💸 هزینه‌ها و مالیات
- ✅ **Expense Management** - مدیریت هزینه‌ها و برداشت‌ها
- ✅ **Expense Categories** - دسته‌بندی هزینه‌ها
- ✅ **VAT System** - سیستم مالیات بر ارزش افزوده
- ✅ **Tax Rates** - نرخ‌های مالیاتی

### 📦 موجودی و بهای تمام شده
- ✅ **COGS Tracking** - محاسبه بهای تمام شده کالای فروش رفته
- ✅ **Cost Entries** - ثبت هزینه‌های موجودی
- ✅ **Multiple Cost Methods** - FIFO, LIFO, AVG

### 🔍 تطبیق و تسویه
- ✅ **Payment Reconciliation** - تطبیق پرداخت‌ها با رسیدها
- ✅ **Bank Reconciliation** - تطبیق موجودی بانک
- ✅ **Settlement System** - تسویه‌حساب

### 📈 گزارش‌گیری
- ✅ **50+ Financial Reports** - بیش از 50 گزارش مالی
- ✅ **General Ledger** - دفتر کل
- ✅ **Trial Balance** - تراز آزمایشی
- ✅ **Balance Sheet** - ترازنامه
- ✅ **Income Statement** - صورت سود و زیان
- ✅ **Cash Flow** - صورت جریان وجوه نقد
- ✅ **Export to Excel/PDF** - خروجی گزارش‌ها

### 🔗 یکپارچه‌سازی
- ✅ **Service API** - API برای سیستم‌های خارجی
- ✅ **Admin API** - API پنل مدیریت
- ✅ **Event-Driven** - معماری رویداد محور

## نصب

### 1. نصب از طریق Composer

```bash
composer require rmscms/accounting
```

### 2. انتشار فایل‌های پیکربندی

```bash
php artisan vendor:publish --tag=accounting-config
```

برای به‌روزرسانی قالب‌ها و فایل‌های استاتیک پکیج (مثلاً فرم دسته هزینه، داشبورد، **سطرهای سند دستی** `manual-journal-lines.js` / `.css`، JS/CSS دیگر):

```bash
php artisan vendor:publish --tag=accounting-views --force
php artisan vendor:publish --tag=accounting-assets --force
```

پس از `accounting-assets` فایل‌ها در `public/vendor/accounting/` قرار می‌گیرند؛ کنترلرها با `withJs` / `withCss` همان مسیر نسبی را لود می‌کنند.

### 3. اجرای Migrations

```bash
php artisan migrate
```

### 4. اجرای Seeders (اختیاری)

```bash
php artisan db:seed --class=RMS\\Accounting\\Database\\Seeders\\AccountsSeeder
php artisan db:seed --class=RMS\\Accounting\\Database\\Seeders\\CurrenciesSeeder
php artisan db:seed --class=RMS\\Accounting\\Database\\Seeders\\PaymentMethodsSeeder
php artisan db:seed --class=RMS\\Accounting\\Database\\Seeders\\TaxRatesSeeder
php artisan db:seed --class=RMS\\Accounting\\Database\\Seeders\\FiscalYearsSeeder
```

یا استفاده از Command نصب:

```bash
php artisan accounting:install
```

## تفاوت «سند دفترکل» و «پیوست فایلی»

| مفهوم | مدل / جدول | توضیح |
|--------|------------|--------|
| سند حسابداری (دفترکل) | `AccountingDocument` | ثبت دوطرفه، وضعیت draft/posted؛ **فایل اسکن نیست**. |
| پیوست خصوصی (رسید، PDF، تصویر) | `AccountingAttachment` + جدول `accounting_attachments` | ذخیره روی دیسک غیرعمومی؛ دانلود فقط از مسیر ادمین احرازشده (`admin/accounting/attachments/{uuid}/download`). |

تنظیمات اندازه و نوع فایل در `config/accounting.php` بخش `attachments` (و متغیرهای محیطی `ACCOUNTING_ATTACHMENTS_*`). فرم هزینه از این سرویس به‌عنوان اولین مصرف‌کننده استفاده می‌کند؛ آپلود AJAX اختیاری از route نام‌گذاری‌شده `admin.accounting.attachments.store` ممکن است.

## پیکربندی

فایل `config/accounting.php`:

```php
return [
    'default_currency' => 'IRR',
    'default_fiscal_year_start' => '01-01', // فروردین
    'default_fiscal_year_end' => '12-29', // اسفند
    'enable_multi_currency' => true,
    'enable_vat' => true,
    'default_vat_rate' => 9,
    'ledger_immutable' => true,
    'cost_method' => 'FIFO', // FIFO, LIFO, AVG
    'reconciliation_required' => true,
];
```

## استفاده

### ثبت یک فاکتور فروش

```php
use RMS\Accounting\Services\CustomerInvoiceService;

$invoiceService = app(CustomerInvoiceService::class);

$invoice = $invoiceService->createInvoice([
    'customer_id' => 1,
    'store_id' => 1,
    'invoice_date' => now(),
    'due_date' => now()->addDays(30),
    'items' => [
        [
            'product_id' => 10,
            'quantity' => 2,
            'unit_price' => 100000,
        ]
    ],
    'tax_rate' => 0.09,
]);

// سند حسابداری به صورت خودکار ثبت می‌شود:
// Debit: Accounts Receivable
// Credit: Sales Revenue
// Credit: VAT Payable
```

### ثبت دریافت پرداخت

```php
use RMS\Accounting\Services\CustomerPaymentService;

$paymentService = app(CustomerPaymentService::class);

$payment = $paymentService->recordPayment([
    'customer_id' => 1,
    'customer_invoice_id' => $invoice->id,
    'amount' => 218000,
    'payment_method_id' => 1, // Cash
    'payment_date' => now(),
    'bank_id' => 1, // اگر بانکی باشد
]);

// سند حسابداری:
// Debit: Bank/Cash
// Credit: Accounts Receivable
```

### ثبت یک فاکتور خرید

```php
use RMS\Accounting\Services\SupplierInvoiceService;

$supplierInvoiceService = app(SupplierInvoiceService::class);

$supplierInvoice = $supplierInvoiceService->createInvoice([
    'supplier_id' => 5,
    'invoice_date' => now(),
    'due_date' => now()->addDays(30),
    'items' => [
        [
            'product_id' => 20,
            'quantity' => 10,
            'unit_price' => 50000,
        ]
    ],
    'currency_code' => 'USD',
]);

// سند حسابداری:
// Debit: Inventory Asset
// Debit: VAT Receivable
// Credit: Accounts Payable
```

### دریافت مانده حساب مشتری

```php
use RMS\Accounting\Services\CustomerBalanceService;

$balanceService = app(CustomerBalanceService::class);

$balance = $balanceService->getBalance(customerId: 1, storeId: 1);

// یا Real-Time از Ledger:
$realTimeBalance = $balanceService->getBalanceFromLedger(customerId: 1, storeId: 1);
```

### محاسبه COGS (بهای تمام شده)

```php
use RMS\Accounting\Services\COGSService;

$cogsService = app(COGSService::class);

// در هنگام فروش، COGS به صورت خودکار محاسبه و ثبت می‌شود
$cogsService->recordCOGS([
    'product_id' => 10,
    'quantity' => 2,
    'sale_reference_id' => $invoice->id,
]);

// سند حسابداری COGS:
// Debit: COGS Expense
// Credit: Inventory Asset
```

## دیتابیس

پکیج شامل **30 جدول** و **2 VIEW** است:

### جداول اصلی:
- `accounts` - حساب‌ها
- `accounting_documents` - اسناد حسابداری
- `financial_ledgers` - دفتر روزنامه (IMMUTABLE)
- `currencies` - ارزها
- `currency_rates` - نرخ‌های تبدیل
- `fiscal_years` - سال‌های مالی

### خزانه‌داری:
- `banks` - بانک‌ها
- `cash_boxes` - صندوق‌ها
- `payment_methods` - روش‌های پرداخت
- `pos_terminals` - کارت‌خوان‌ها
- `cheques` - چک‌ها
- `wallets` - کیف پول‌ها
- `wallet_transactions` - تراکنش‌های کیف پول

### دریافتنی:
- `customer_invoices` - فاکتورهای فروش
- `customer_payments` - دریافت‌ها
- `customer_balances` - مانده مشتریان (Cache)

### پرداختنی:
- `suppliers` - تامین‌کننده‌ها
- `purchase_orders` - سفارش‌های خرید
- `purchase_order_items` - آیتم‌های سفارش
- `supplier_invoices` - فاکتورهای خرید
- `supplier_invoice_items` - آیتم‌های فاکتور
- `supplier_payments` - پرداخت‌ها

### سایر:
- `cost_entries` - COGS
- `tax_rates` - نرخ‌های مالیاتی
- `expense_categories` - دسته‌بندی هزینه‌ها
- `expenses` - هزینه‌ها
- `expense_items` - آیتم‌های هزینه
- `payment_reconciliations` - تطبیق پرداخت‌ها
- `settlements` - تسویه‌حساب‌ها

### VIEWs:
- `customer_balances_view` - محاسبه Real-Time مانده از Ledger
- `supplier_balances_view` - محاسبه Real-Time مانده تامین‌کننده‌ها

### Danger zone — `accounting:wipe`

دستور `php artisan accounting:wipe` برای **حذف اسناد، دفتر مالی (`financial_ledgers`)، دفتر دستی، سال‌های مالی** و در حالت تهاجمی **حذف گستردهٔ تراکنش‌های عملیاتی** است. پیش‌فرض فقط **dry-run** است (تا زمانی که `--execute` ندهید، دیتابیس عوض نمی‌شود).

- `--mode=documents` (پیش‌فرض): حذف هستهٔ GL؛ روی رکوردهای عملیاتی که نگه داشته می‌شوند فقط `document_id` / `accounting_document_id` خالی می‌شود. بعد از آن، رکوردهای تجاری ممکن است **بدون سند معتبر** بمانند؛ باید دوباره از مسیر عادی سیستم پست شوند یا دستی اصلاح شوند.
- `--mode=accounting-reset`: علاوه بر موارد بالا، حذف جداول عملیاتی (فاکتور، پرداخت، PO، …) با **حفظ** `accounts`، `banks`، `cash_boxes`، `pos_terminals`. برای اجرای واقعی باید `--execute` به‌همراه **`--confirm=RESET`** یا **`--force`** بدهید.

نمونهٔ فقط گزارش:

```bash
php artisan accounting:wipe --mode=documents
```

اجرای واقعی (غیرقابل بازگشت):

```bash
php artisan accounting:wipe --mode=documents --execute
php artisan accounting:wipe --mode=accounting-reset --execute --confirm=RESET
```

## Commands

```bash
# نصب
php artisan accounting:install

# بستن سال مالی
php artisan accounting:close-fiscal-year --year=1403

# یادآوری چک‌های سررسید
php artisan accounting:cheque-reminder --days=7

# به‌روزرسانی نرخ ارز
php artisan accounting:update-exchange-rates

# تطبیق خودکار
php artisan accounting:auto-reconcile --bank=1 --date=2025-01-08

# محاسبه مجدد مانده‌ها
php artisan accounting:recalculate-balances
```

## Service API

برای استفاده در سیستم‌های خارجی:

```http
GET /api/v1/accounting/customers/{id}/balance
POST /api/v1/accounting/invoices
POST /api/v1/accounting/payments
GET /api/v1/accounting/suppliers/{id}/balance
GET /api/v1/accounting/ledger
```

## Events

پکیج شامل Eventهای زیر است:

- `PaymentReceived` - دریافت پرداخت
- `PaymentMade` - انجام پرداخت
- `InvoiceCreated` - ایجاد فاکتور
- `SettlementCreated` - تسویه‌حساب
- `ChequeIssued` - صدور چک
- `ChequeCashed` - وصول چک
- `ChequeBounced` - برگشت چک
- `FiscalYearClosed` - بستن سال مالی
- `CurrencyRateUpdated` - به‌روزرسانی نرخ ارز

## نکات مهم

### 1. Immutable Ledger
هیچ‌وقت جدول `financial_ledgers` را UPDATE یا DELETE نکنید.

### 2. Double Entry
هر سند حسابداری حداقل 2 entry در Ledger دارد (Debit + Credit).

### 3. FX Rate
در تراکنش‌های ارزی، اختلاف تسعیر به صورت خودکار محاسبه و ثبت می‌شود.

## لایسنس

MIT License - برای جزئیات بیشتر فایل [LICENSE](LICENSE) را ببینید.

## پشتیبانی

برای گزارش مشکلات یا پیشنهادات، لطفاً از [GitHub Issues](https://github.com/rmscms/accounting/issues) استفاده کنید.
