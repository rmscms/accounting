# مستندات سیستم مالیاتی (Tax System Documentation)

## 📋 فهرست مطالب
1. [معرفی](#معرفی)
2. [نصب و راه‌اندازی](#نصب-و-راه-اندازی)
3. [تنظیمات](#تنظیمات)
4. [استفاده در کد](#استفاده-در-کد)
5. [API Endpoints](#api-endpoints)
6. [مثال‌های کاربردی](#مثال-های-کاربردی)

---

## معرفی

سیستم مالیاتی پکیج حسابداری RMS برای مدیریت و محاسبه خودکار مالیات‌ها طراحی شده است:

### ویژگی‌ها:
✅ محاسبه خودکار مالیات بر ارزش افزوده (VAT)
✅ محاسبه مالیات بر درآمد (Income Tax)
✅ پشتیبانی از چند نرخ مالیاتی (استاندارد، کاهش یافته، معاف)
✅ روش‌های محاسبه Exclusive و Inclusive
✅ ثبت خودکار در دفتر کل
✅ معافیت مالیاتی برای مشتریان/محصولات
✅ گزارش‌های مالیاتی کامل

---

## نصب و راه‌اندازی

### 1. اجرای Migration

```bash
php artisan migrate
```

این Migration فیلدهای زیر را اضافه می‌کند:
- `customer_invoices.tax_method` (exclusive/inclusive)
- `customer_invoices.tax_rate` (درصد مالیات)
- `supplier_invoices.tax_method`
- `supplier_invoices.tax_rate`
- `customers.tax_exempt` (معاف از مالیات)
- `suppliers.tax_exempt`

### 2. بارگذاری Helper Functions

فایل `tax_helpers.php` را در `composer.json` اضافه کنید:

```json
"autoload": {
    "files": [
        "src/Helpers/tax_helpers.php"
    ]
}
```

سپس:
```bash
composer dump-autoload
```

### 3. تنظیمات اولیه

به صفحه تنظیمات بروید:
```
/admin/accounting/settings
```

و موارد زیر را تنظیم کنید:
- نرخ VAT (معمولاً 9%)
- حساب مالیات پرداختنی
- حساب مالیات دریافتنی
- روش محاسبه (Exclusive/Inclusive)

---

## تنظیمات

### تنظیمات VAT

```php
// فعال/غیرفعال کردن VAT
Setting::set('accounting.vat.enabled', true);

// نرخ استاندارد (9%)
Setting::set('accounting.vat.rate', 9);

// نرخ کاهش یافته
Setting::set('accounting.vat.rate_reduced', 5);

// نرخ معاف
Setting::set('accounting.vat.rate_zero', 0);

// روش محاسبه (exclusive یا inclusive)
Setting::set('accounting.tax.calculation_method', 'exclusive');

// حساب‌های مالیاتی
Setting::set('accounting.vat.account_payable_id', 123); // مالیات فروش
Setting::set('accounting.vat.account_receivable_id', 456); // مالیات خرید
```

### تنظیمات Income Tax

```php
// فعال/غیرفعال
Setting::set('accounting.income_tax.enabled', true);

// نرخ (25%)
Setting::set('accounting.income_tax.rate', 25);

// حساب مالیات
Setting::set('accounting.income_tax.account_id', 789);
```

### تنظیمات Rounding

```php
// round: گرد کردن عادی
// ceil: گرد کردن به بالا
// floor: گرد کردن به پایین
Setting::set('accounting.tax.rounding', 'round');
```

---

## استفاده در کد

### 1. Helper Functions (ساده‌ترین روش)

```php
// دریافت تنظیمات
$vatRate = tax_settings('vat.rate'); // 9
$allSettings = tax_settings(); // همه تنظیمات

// محاسبه سریع VAT
$result = calculate_vat(100000); // مبلغ 100,000
// نتیجه: ['tax_amount' => 9000, 'total_amount' => 109000, 'base_amount' => 100000]

// فرمت کردن مالیات
echo format_tax(9000); // "9,000 ریال"

// دریافت نرخ VAT
$standardRate = vat_rate('standard'); // 9
$reducedRate = vat_rate('reduced'); // 5

// بررسی فعال بودن
if (is_vat_enabled()) {
    // VAT فعال است
}
```

### 2. استفاده از TaxCalculator

```php
use RMS\Accounting\Services\Tax\TaxCalculator;

// محاسبه VAT با نرخ سفارشی
$result = TaxCalculator::calculateVAT(100000, 9, 'exclusive');
// نتیجه:
// [
//     'tax_amount' => 9000,
//     'total_amount' => 109000,
//     'base_amount' => 100000,
//     'tax_rate' => 9,
//     'method' => 'exclusive'
// ]

// محاسبه مالیات بر درآمد
$result = TaxCalculator::calculateIncomeTax(500000, 25);
// نتیجه:
// [
//     'tax_amount' => 125000,
//     'net_income' => 375000,
//     'tax_rate' => 25
// ]

// محاسبه چند آیتم
$items = [
    ['amount' => 100000, 'tax_rate' => 9],
    ['amount' => 50000, 'tax_rate' => 5],
];
$result = TaxCalculator::calculateMultipleItems($items);
```

### 3. استفاده از TaxService

```php
use RMS\Accounting\Services\TaxService;

$taxService = app(TaxService::class);

// اعمال مالیات به Invoice
$invoice = CustomerInvoice::find(1);
$invoice = $taxService->applyVATToCustomerInvoice($invoice);
$invoice->save();

// ثبت مالیات در دفتر کل
$ledgerService = app(LedgerService::class);
$taxService->recordTaxInLedger($invoice, $ledgerService);

// محاسبه مالیات قابل پرداخت
$vatPayable = $taxService->calculateVATPayable('2026-01-01', '2026-01-31');

// بررسی معافیت مالیاتی
if ($taxService->isExemptFromTax($customer)) {
    // مشتری معاف از مالیات است
}
```

---

## API Endpoints

### 1. دریافت تنظیمات مالیاتی

```http
GET /api/accounting/tax/settings
```

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "vat": {
      "enabled": true,
      "rate": 9,
      "rate_reduced": 5,
      "rate_zero": 0,
      "method": "exclusive"
    },
    "income_tax": {
      "enabled": true,
      "rate": 25
    }
  }
}
```

### 2. محاسبه VAT

```http
POST /api/accounting/tax/calculate-vat
Content-Type: application/json

{
  "amount": 100000,
  "tax_rate": 9,
  "method": "exclusive"
}
```

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "tax_amount": 9000,
    "total_amount": 109000,
    "base_amount": 100000,
    "tax_rate": 9
  }
}
```

### 3. دریافت نرخ‌های VAT

```http
GET /api/accounting/tax/vat-rates
```

### 4. محاسبه مالیات قابل پرداخت

```http
GET /api/accounting/tax/vat-payable?start_date=2026-01-01&end_date=2026-01-31
```

### 5. محاسبه چند آیتم

```http
POST /api/accounting/tax/calculate-multiple
Content-Type: application/json

{
  "items": [
    {"amount": 100000, "tax_rate": 9},
    {"amount": 50000, "tax_rate": 5}
  ]
}
```

---

## مثال‌های کاربردی

### مثال 1: محاسبه مالیات فاکتور

```php
use RMS\Accounting\Services\TaxService;

// ایجاد فاکتور جدید
$invoice = new CustomerInvoice([
    'customer_id' => 1,
    'invoice_number' => 'INV-001',
    'invoice_date' => now(),
    'subtotal' => 100000,
]);

// اعمال خودکار مالیات
$taxService = app(TaxService::class);
$invoice = $taxService->applyVATToCustomerInvoice($invoice);

// ذخیره
$invoice->save();

// نتیجه:
// subtotal: 100,000
// tax_amount: 9,000
// total_amount: 109,000
```

### مثال 2: فاکتور با چند آیتم

```php
$invoice = CustomerInvoice::create([...]);

// اضافه کردن آیتم‌ها
$invoice->items()->create([
    'product_id' => 1,
    'quantity' => 2,
    'price' => 50000,
    'tax_rate' => 9, // نرخ استاندارد
]);

$invoice->items()->create([
    'product_id' => 2,
    'quantity' => 1,
    'price' => 30000,
    'tax_rate' => 0, // معاف
]);

// اعمال مالیات
$taxService->applyVATToCustomerInvoice($invoice);
$invoice->save();
```

### مثال 3: مشتری معاف از مالیات

```php
$customer = Customer::find(1);
$customer->tax_exempt = true;
$customer->save();

// بررسی معافیت
if ($taxService->isExemptFromTax($customer)) {
    // مالیات محاسبه نمی‌شود
}
```

### مثال 4: محاسبه Tax Inclusive

```php
// قیمت نهایی شامل مالیات است
$result = calculate_vat(109000, 9, 'inclusive');

// نتیجه:
// base_amount: 100,000 (قیمت بدون مالیات)
// tax_amount: 9,000
// total_amount: 109,000
```

### مثال 5: گزارش مالیاتی

```php
use RMS\Accounting\Services\ReportService;

$reportService = app(ReportService::class);

$report = $reportService->getVATReport([
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
]);

// نتیجه:
// [
//     'output_vat' => ['sales' => 1000000, 'vat' => 90000],
//     'input_vat' => ['purchases' => 500000, 'vat' => 45000],
//     'vat_payable' => 45000,
// ]
```

---

## روش‌های محاسبه

### Tax Exclusive (پیش‌فرض)
قیمت **بدون** مالیات است و مالیات به آن اضافه می‌شود.

```
قیمت پایه: 100,000
مالیات 9%: 9,000
───────────────────
قیمت نهایی: 109,000
```

**کاربرد:** B2B، فاکتورهای تجاری

### Tax Inclusive
قیمت **شامل** مالیات است و باید مالیات استخراج شود.

```
قیمت نهایی: 109,000
مالیات 9%: 9,000
───────────────────
قیمت پایه: 100,000
```

**فرمول:** `base = total / (1 + rate/100)`

**کاربرد:** B2C، قیمت‌گذاری خرده‌فروشی

---

## معافیت مالیاتی

### تعریف معافیت برای مشتری

```php
$customer->tax_exempt = true;
$customer->save();
```

### تعریف معافیت برای تامین‌کننده

```php
$supplier->tax_exempt = true;
$supplier->save();
```

### بررسی معافیت

```php
if ($taxService->isExemptFromTax($customer)) {
    // معاف است - مالیات صفر
}
```

---

## نرخ‌های مالیاتی

| نوع | نرخ پیش‌فرض | توضیحات |
|-----|------------|----------|
| **Standard** | 9% | نرخ استاندارد ایران |
| **Reduced** | 0-9% | نرخ کاهش یافته برای کالاهای خاص |
| **Zero** | 0% | معاف از مالیات |

### استفاده:

```php
$standardRate = vat_rate('standard'); // 9
$reducedRate = vat_rate('reduced'); // 5
$zeroRate = vat_rate('zero'); // 0
```

---

## ثبت مالیات در دفتر کل

### فروش (Sales)

```
بدهکار: حساب دریافتنی (مشتری)      109,000
    بستانکار: درآمد فروش                100,000
    بستانکار: مالیات پرداختنی (VAT)      9,000
```

### خرید (Purchase)

```
بدهکار: هزینه/کالا                   100,000
بدهکار: مالیات دریافتنی (VAT)          9,000
    بستانکار: حساب پرداختنی             109,000
```

---

## Validation Rules

### تنظیمات

```php
$request->validate([
    'vat_rate' => 'required|numeric|min:0|max:100',
    'vat_account_payable_id' => 'required|exists:accounts,id',
    'tax_calculation_method' => 'required|in:exclusive,inclusive',
]);
```

### Invoice

```php
$request->validate([
    'tax_method' => 'nullable|in:exclusive,inclusive',
    'tax_rate' => 'nullable|numeric|min:0|max:100',
]);
```

---

## گزارش‌های مالیاتی

### 1. گزارش VAT کامل

```php
$report = $reportService->getVATReport([
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
]);
```

### 2. مالیات پرداختنی

```php
$report = $reportService->getVATPayable($filters);
```

### 3. مالیات دریافتنی

```php
$report = $reportService->getVATReceivable($filters);
```

---

## نکات مهم

### ⚠️ Double Entry
همیشه مالیات در **دو طرف** ثبت می‌شود:
- فروش: بستانکار در حساب مالیات پرداختنی
- خرید: بدهکار در حساب مالیات دریافتنی

### ⚠️ معادله مالیات قابل پرداخت

```
مالیات قابل پرداخت = مالیات فروش - مالیات خرید
```

اگر عدد منفی شد = مالیات قابل دریافت (استرداد)

### ⚠️ گرد کردن

همیشه از `TaxCalculator::applyRounding()` استفاده کنید تا گرد کردن یکنواخت باشد.

---

## خطاهای رایج و راه‌حل

### خطا: "حساب مالیاتی تنظیم نشده"

**راه‌حل:** به `/admin/accounting/settings` بروید و حساب‌های مالیاتی را انتخاب کنید.

### خطا: "مالیات محاسبه نمی‌شود"

**راه‌حل:** بررسی کنید:
1. `accounting.vat.enabled` = true
2. `vat_rate` بزرگتر از 0
3. مشتری `tax_exempt` نباشد

### خطا: "مالیات دوبار حساب می‌شود"

**راه‌حل:** از Observer یا Manual استفاده کنید، نه هر دو!

---

## Performance Tips

### 1. Cache تنظیمات

تنظیمات به صورت خودکار cache می‌شوند (24 ساعت).

برای clear کردن:
```php
Setting::clearCache();
```

### 2. Bulk Calculation

برای محاسبه چند آیتم از `calculateMultipleItems` استفاده کنید:

```php
$items = [...]; // 100 آیتم
$result = TaxCalculator::calculateMultipleItems($items);
// سریع‌تر از loop
```

---

## مثال کامل: فلوی ثبت فاکتور

```php
use RMS\Accounting\Services\{TaxService, LedgerService};
use RMS\Accounting\Models\CustomerInvoice;

// 1. ایجاد فاکتور
$invoice = CustomerInvoice::create([
    'customer_id' => 1,
    'invoice_number' => 'INV-001',
    'invoice_date' => now(),
    'subtotal' => 0, // محاسبه می‌شود
]);

// 2. اضافه کردن آیتم‌ها
$invoice->items()->create([
    'product_id' => 1,
    'quantity' => 2,
    'price' => 50000,
]);

// 3. محاسبه مالیات
$taxService = app(TaxService::class);
$invoice = $taxService->applyVATToCustomerInvoice($invoice);
$invoice->save();

// 4. ثبت در دفتر کل
$ledgerService = app(LedgerService::class);

// ثبت فروش
$ledgerService->recordSale($invoice);

// ثبت مالیات
$taxService->recordTaxInLedger($invoice, $ledgerService);

// 5. نتیجه نهایی
echo "مبلغ پایه: " . number_format($invoice->subtotal);      // 100,000
echo "مالیات: " . number_format($invoice->tax_amount);       // 9,000
echo "جمع کل: " . number_format($invoice->total_amount);     // 109,000
```

---

## Support

برای سوالات و مشکلات:
- 📧 Email: support@rms.ir
- 📖 Documentation: /docs/accounting/tax
- 🐛 Issues: github.com/rms/accounting/issues

---

**تهیه شده توسط:** RMS Accounting Package
**نسخه:** 1.0.0
**تاریخ:** 2026-01-24
