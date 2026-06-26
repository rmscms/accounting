# 📊 Income Tax (مالیات بر درآمد) - استاندارد جهانی

## 🎯 تفاوت VAT و Income Tax

### ❌ اشتباه رایج
بسیاری فکر می‌کنند Income Tax مثل VAT در هر Invoice محاسبه می‌شود - **این اشتباه است!**

### ✅ استاندارد جهانی

| مشخصه | VAT | Income Tax |
|-------|-----|------------|
| **زمان محاسبه** | هر فاکتور | پایان سال مالی |
| **مبنا** | قیمت فروش | سود خالص |
| **پرداخت‌کننده** | خریدار | شرکت |
| **ثبت در** | Invoice | صورت سود و زیان |
| **فرکانس** | ماهانه/فصلی | سالانه |
| **Double Entry** | در Invoice | در بستن سال مالی |

---

## 📐 فرمول محاسبه

### VAT (مالیات بر ارزش افزوده):
```
قیمت × نرخ VAT = مالیات VAT
100,000 × 9% = 9,000 ریال
```

### Income Tax (مالیات بر درآمد):
```
(درآمد - هزینه) × نرخ Income Tax = مالیات بر درآمد
(10,000,000 - 6,000,000) × 25% = 1,000,000 ریال
```

---

## 🔄 فلوی محاسبه Income Tax

### مرحله 1: طول سال مالی
```
ثبت فاکتورها با VAT
↓
ثبت هزینه‌ها
↓
ثبت در دفتر کل
```

### مرحله 2: پایان سال مالی
```
1. محاسبه صورت سود و زیان
   ├─ جمع درآمد: 10,000,000
   ├─ بهای تمام شده: 4,000,000
   ├─ سود ناخالص: 6,000,000
   ├─ هزینه‌های عملیاتی: 2,000,000
   └─ سود قبل از مالیات: 4,000,000

2. محاسبه Income Tax
   ├─ سود قبل از مالیات: 4,000,000
   ├─ نرخ Income Tax: 25%
   └─ هزینه مالیات: 1,000,000

3. سود خالص (پس از مالیات)
   └─ 4,000,000 - 1,000,000 = 3,000,000
```

### مرحله 3: ثبت در دفتر کل
```
سند بستن سال:
  بدهکار: هزینه مالیات بر درآمد     1,000,000
    بستانکار: مالیات پرداختنی        1,000,000
```

---

## 💻 پیاده‌سازی در کد

### 1️⃣ در صورت سود و زیان (Income Statement)

```php
// ReportService.php
public function getIncomeStatement(array $filters = []): array
{
    // محاسبات معمول...
    $incomeBeforeTax = $totalRevenue - $totalExpenses;
    
    // ⭐ محاسبه Income Tax
    $incomeTaxRate = Setting::get('accounting.income_tax.rate', 25);
    $incomeTaxExpense = $incomeBeforeTax * ($incomeTaxRate / 100);
    
    // ⭐ سود پس از مالیات
    $netIncome = $incomeBeforeTax - $incomeTaxExpense;
    
    return [
        'income_before_tax' => $incomeBeforeTax,
        'income_tax_expense' => $incomeTaxExpense,
        'net_income' => $netIncome,
    ];
}
```

### 2️⃣ بستن سال مالی

```php
use RMS\Accounting\Services\FiscalYearClosingService;

$closingService = app(FiscalYearClosingService::class);
$fiscalYear = FiscalYear::current();

// بستن سال و ثبت Income Tax
$result = $closingService->closeFiscalYear($fiscalYear);

// نتیجه:
// - income_before_tax: 4,000,000
// - income_tax_expense: 1,000,000
// - net_income: 3,000,000
// - income_tax_document_id: 123
```

### 3️⃣ فقط محاسبه (بدون ثبت)

```php
// برای Preview
$calculation = $closingService->calculateIncomeTax($fiscalYear);

// نتیجه:
// [
//     'total_revenue' => 10,000,000,
//     'total_expenses' => 6,000,000,
//     'income_before_tax' => 4,000,000,
//     'income_tax_rate' => 25,
//     'income_tax_expense' => 1,000,000,
//     'net_income' => 3,000,000,
// ]
```

---

## 📋 صورت سود و زیان (Income Statement)

### فرمت استاندارد:

```
درآمد فروش                          10,000,000
بهای تمام شده کالای فروش رفته      (4,000,000)
─────────────────────────────────────────────
سود ناخالص                           6,000,000

هزینه‌های عملیاتی                   (2,000,000)
─────────────────────────────────────────────
سود عملیاتی                          4,000,000

سود (زیان) قبل از مالیات            4,000,000
مالیات بر درآمد (25%)                (1,000,000)
─────────────────────────────────────────────
سود (زیان) خالص                     3,000,000
═════════════════════════════════════════════
```

---

## 🏦 Double Entry برای Income Tax

### ثبت سند:

```
تاریخ: پایان سال مالی
شرح: مالیات بر درآمد سال 1404

بدهکار: هزینه مالیات بر درآمد (52-99)    1,000,000
  بستانکار: مالیات بر درآمد پرداختنی (2-5-3)  1,000,000
```

### حساب‌های استاندارد:

| حساب | کد | نوع | توضیحات |
|------|-----|-----|---------|
| **هزینه مالیات بر درآمد** | 52-99 | Expense | بدهکار در بستن سال |
| **مالیات بر درآمد پرداختنی** | 2-5-3 | Liability | بستانکار تا پرداخت |

---

## ⚙️ تنظیمات

### صفحه تنظیمات:

```
/admin/accounting/settings → تب مالیات

✅ فعال‌سازی مالیات بر درآمد
📊 نرخ مالیات: 25%
💳 حساب هزینه مالیات بر درآمد: [انتخاب]
💳 حساب مالیات پرداختنی: [انتخاب]
```

### Via Code:

```php
use RMS\Core\Models\Setting;

// فعال کردن
Setting::set('accounting.income_tax.enabled', true);

// نرخ
Setting::set('accounting.income_tax.rate', 25);

// حساب هزینه (Expense)
Setting::set('accounting.income_tax.expense_account_id', 123);

// حساب پرداختنی (Liability)
Setting::set('accounting.income_tax.payable_account_id', 456);
```

---

## 📊 گزارش‌ها

### 1. صورت سود و زیان

```php
$reportService = app(ReportService::class);
$report = $reportService->getIncomeStatement([
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
]);

echo "سود قبل از مالیات: " . $report['income_before_tax'];
echo "هزینه مالیات: " . $report['income_tax_expense'];
echo "سود خالص: " . $report['net_income'];
```

### 2. محاسبه Income Tax

```php
$closingService = app(FiscalYearClosingService::class);
$fiscalYear = FiscalYear::where('name', '1404')->first();

$calculation = $closingService->calculateIncomeTax($fiscalYear);

// Preview قبل از بستن سال
```

---

## 🌍 نرخ‌های جهانی

| کشور | نرخ استاندارد |
|------|---------------|
| **ایران** | 25% |
| آمریکا | 21% |
| انگلستان | 25% |
| آلمان | 30% |
| امارات | 9% |
| عربستان | 20% |
| ترکیه | 25% |

---

## ⚠️ نکات مهم

### 1️⃣ Income Tax ≠ VAT
- **VAT:** در Invoice، خریدار پرداخت می‌کنه
- **Income Tax:** در سال مالی، شرکت از سود خودش پرداخت می‌کنه

### 2️⃣ زمان ثبت
- Income Tax فقط **یکبار در سال** ثبت می‌شه
- در زمان بستن سال مالی

### 3️⃣ مبنای محاسبه
- مبنا: **سود قبل از مالیات** (EBT - Earnings Before Tax)
- نه درآمد ناخالص

### 4️⃣ Double Entry
```
بدهکار: هزینه مالیات (کاهش سود)
بستانکار: مالیات پرداختنی (بدهی)
```

### 5️⃣ پرداخت
بعد از بستن سال:
```
بدهکار: مالیات پرداختنی
  بستانکار: بانک
```

---

## 📝 مثال کامل

### سناریو: شرکت ABC

**سال مالی 1404:**

#### طول سال:
```php
// ثبت Invoice ها (با VAT)
CustomerInvoice::create([
    'subtotal' => 100,000,
    'tax_amount' => 9,000, // VAT 9%
    'total_amount' => 109,000,
]);

// جمع درآمد سال: 10,000,000
// جمع هزینه سال: 6,000,000
```

#### پایان سال (1404/12/29):
```php
$closingService = app(FiscalYearClosingService::class);
$fiscalYear = FiscalYear::where('name', '1404')->first();

// 1. محاسبه
$calc = $closingService->calculateIncomeTax($fiscalYear);
// income_before_tax: 4,000,000
// income_tax_expense: 1,000,000 (25%)
// net_income: 3,000,000

// 2. بستن سال و ثبت
$result = $closingService->closeFiscalYear($fiscalYear);

// سند ثبت شد:
// بدهکار: هزینه مالیات    1,000,000
// بستانکار: مالیات پرداختنی 1,000,000
```

#### پرداخت مالیات (1405/03/15):
```php
// ثبت پرداخت
$ledgerService->recordTransaction([
    'description' => 'پرداخت مالیات بر درآمد سال 1404',
], [
    [
        'account_id' => $incomeTaxPayableAccount,
        'debit' => 1,000,000,
        'credit' => 0,
    ],
    [
        'account_id' => $bankAccount,
        'debit' => 0,
        'credit' => 1,000,000,
    ],
]);
```

---

## ✅ Checklist پیاده‌سازی

- [x] محاسبه در Income Statement
- [x] ثبت در بستن سال مالی
- [x] FiscalYearClosingService
- [x] تنظیمات (2 حساب)
- [x] View صورت سود و زیان
- [x] حذف از Invoice Observer ها
- [x] مستندات کامل

---

## 🎓 خلاصه

✅ **Income Tax = مالیات سالانه روی سود**  
✅ **محاسبه در پایان سال مالی**  
✅ **ثبت در صورت سود و زیان**  
✅ **Double Entry در بستن سال**  
✅ **استاندارد جهانی: 20-30%**  

---

**تاریخ:** 2026-01-24  
**استاندارد:** GAAP/IFRS  
**وضعیت:** ✅ پیاده‌سازی شده
