# ✅ Income Tax به جای درست منتقل شد!

## 🎯 تغییرات انجام شده

### ❌ قبل (اشتباه):
```
Invoice → محاسبه Income Tax ❌
Observer → اعمال Income Tax به هر فاکتور ❌
```

### ✅ بعد (استاندارد جهانی):
```
Income Statement → محاسبه Income Tax ✅
Fiscal Year Closing → ثبت Income Tax در پایان سال ✅
```

---

## 📦 فایل‌های آپدیت شده

### 1️⃣ ReportService.php
```php
// صورت سود و زیان حالا Income Tax داره:
'income_before_tax' => 4,000,000  // سود قبل از مالیات
'income_tax_expense' => 1,000,000 // هزینه مالیات (25%)
'net_income' => 3,000,000         // سود پس از مالیات
```

### 2️⃣ FiscalYearClosingService.php (جدید) ⭐
```php
// سرویس جدید برای بستن سال مالی:
- closeFiscalYear()          // بستن کامل سال
- recordIncomeTaxExpense()   // ثبت مالیات
- transferToRetainedEarnings() // انتقال به سود انباشته
- calculateIncomeTax()       // فقط محاسبه
```

### 3️⃣ income-statement.blade.php
```blade
<!-- نمایش در View: -->
سود قبل از مالیات: 4,000,000
مالیات بر درآمد (25%): (1,000,000)
─────────────────────────────────
سود خالص: 3,000,000
```

### 4️⃣ settings/index.blade.php
```blade
<!-- تنظیمات جدید: -->
✅ حساب هزینه مالیات بر درآمد (Expense)
✅ حساب مالیات پرداختنی (Liability)
✅ هشدار: محاسبه در پایان سال
```

### 5️⃣ SettingsController.php
```php
// فیلدهای جدید:
'income_tax_expense_account_id'  // حساب بدهکار
'income_tax_payable_account_id'  // حساب بستانکار
```

### 6️⃣ AccountingServiceProvider.php
```php
// سرویس جدید اضافه شد:
$this->app->singleton(FiscalYearClosingService::class);
```

---

## 📊 نحوه استفاده

### 1. محاسبه در صورت سود و زیان

```php
$reportService = app(ReportService::class);
$incomeStatement = $reportService->getIncomeStatement([
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
]);

// نتیجه:
// - income_before_tax: سود قبل از مالیات
// - income_tax_expense: هزینه مالیات
// - net_income: سود پس از مالیات
```

### 2. بستن سال مالی و ثبت مالیات

```php
$closingService = app(FiscalYearClosingService::class);
$fiscalYear = FiscalYear::current();

// بستن سال و ثبت مالیات
$result = $closingService->closeFiscalYear($fiscalYear);

// این کار:
// 1. صورت سود و زیان رو محاسبه می‌کنه
// 2. Income Tax رو ثبت می‌کنه (Double Entry)
// 3. سود خالص رو به سود انباشته منتقل می‌کنه
// 4. سال مالی رو می‌بنده
```

### 3. فقط محاسبه (Preview)

```php
$calculation = $closingService->calculateIncomeTax($fiscalYear);

// برای دیدن Income Tax بدون ثبت
```

---

## 🏦 Double Entry

### سند بستن سال:

```
تاریخ: پایان سال مالی 1404
شرح: مالیات بر درآمد سال مالی 1404

بدهکار: هزینه مالیات بر درآمد        1,000,000
  بستانکار: مالیات بر درآمد پرداختنی    1,000,000
```

---

## ⚙️ تنظیمات

### قبل از استفاده:

1. برو به `/admin/accounting/settings`
2. تب **مالیات**
3. بخش **مالیات بر درآمد**:
   - ✅ فعال کن
   - 📊 نرخ: 25%
   - 💳 حساب هزینه (Expense)
   - 💳 حساب پرداختنی (Liability)

---

## 📈 مثال کامل

### سال 1404:

```
طول سال:
- درآمد: 10,000,000
- هزینه: 6,000,000

پایان سال:
- سود قبل از مالیات: 4,000,000
- Income Tax (25%): 1,000,000
- سود خالص: 3,000,000

ثبت در دفتر کل:
  بدهکار: هزینه مالیات    1,000,000
    بستانکار: مالیات پرداختنی 1,000,000
```

---

## 🔄 تفاوت با VAT

| | VAT | Income Tax |
|---|-----|------------|
| **زمان** | هر Invoice | پایان سال |
| **مبنا** | قیمت فروش | سود خالص |
| **کی پرداخت می‌کنه** | خریدار | شرکت |
| **ثبت کجا** | Invoice | Income Statement |
| **فرکانس** | ماهانه | سالانه |

---

## ✅ Checklist

- [x] حذف Income Tax از Invoice Observer ها
- [x] اضافه Income Tax به Income Statement
- [x] ساخت FiscalYearClosingService
- [x] آپدیت صفحه تنظیمات (2 حساب)
- [x] آپدیت View صورت سود و زیان
- [x] مستندات کامل (INCOME_TAX_STANDARD.md)
- [x] مثال‌های کاربردی

---

## 🎓 خلاصه

### VAT (مالیات بر ارزش افزوده):
✅ **در هر Invoice**  
✅ **خریدار پرداخت می‌کنه**  
✅ **روی قیمت فروش**  

### Income Tax (مالیات بر درآمد):
✅ **پایان سال مالی**  
✅ **شرکت از سود خودش پرداخت می‌کنه**  
✅ **روی سود خالص**  

---

**استاندارد جهانی اعمال شد! 🌍**

- GAAP ✅
- IFRS ✅
- Iran Accounting Standard ✅

---

**تاریخ:** 2026-01-24  
**وضعیت:** ✅ کامل شده
