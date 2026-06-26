# 🔒 Tax Rate History & Immutability

## مشکل: تغییر نرخ مالیات در وسط سال مالی

### سناریو:
```
1 فروردین 1404: نرخ VAT = 9%
  ↓
  Invoice #001: 100,000 → مالیات 9,000
  Invoice #002: 50,000  → مالیات 4,500
  
15 خرداد 1404: نرخ VAT تغییر کرد به 12%
  ↓
  Invoice #003: 80,000  → مالیات 9,600
  
1 تیر 1404: کاربر Invoice #001 را Edit می‌کند
  ⚠️ اگر از Settings استفاده کنیم → مالیات 12% می‌شود (اشتباه!)
  ✅ اگر از tax_rate ذخیره شده استفاده کنیم → مالیات 9% باقی می‌ماند (درست!)
```

---

## ✅ راه‌حل پیاده‌سازی شده

### 1️⃣ ذخیره نرخ در زمان ایجاد Invoice

```php
// Observer: CustomerInvoiceObserver.php
public function saving(CustomerInvoice $invoice): void
{
    // ⭐ CRITICAL: ذخیره نرخ در لحظه ایجاد
    if (!$invoice->exists || $invoice->tax_rate === null) {
        // نرخ فعلی از Settings
        $invoice->tax_rate = tax_settings('vat.rate', 9);
        $invoice->tax_method = tax_settings('vat.method', 'exclusive');
    }
    
    // محاسبه با نرخ ذخیره شده
    $this->taxService->applyVATToCustomerInvoice($invoice);
}
```

### 2️⃣ استفاده از نرخ ذخیره شده در محاسبات

```php
// TaxService.php
public function applyVATToCustomerInvoice(CustomerInvoice $invoice): CustomerInvoice
{
    // ⭐ استفاده از نرخ ذخیره شده، نه Settings
    $invoiceTaxRate = $invoice->tax_rate ?? TaxCalculator::getVATRate('standard');
    $invoiceTaxMethod = $invoice->tax_method ?? tax_settings('vat.method', 'exclusive');
    
    // محاسبه با نرخ Invoice
    $result = TaxCalculator::calculateVAT($amount, $invoiceTaxRate, $invoiceTaxMethod);
    
    return $invoice;
}
```

---

## 🔍 فلوی کامل

### ایجاد Invoice جدید:

```
1. Invoice::create([...])
    ↓
2. Observer::saving() فراخوانی می‌شود
    ↓
3. چک می‌کنه: !$invoice->exists ← Invoice جدید است
    ↓
4. نرخ فعلی از Settings بگیر: tax_rate = 9
    ↓
5. در Invoice ذخیره کن: $invoice->tax_rate = 9
    ↓
6. محاسبه مالیات با نرخ 9%
    ↓
7. Invoice ذخیره می‌شود با tax_rate = 9
```

### Edit کردن Invoice قدیمی:

```
1. $invoice = Invoice::find(1); // دارای tax_rate = 9
2. $invoice->subtotal = 120000;
3. $invoice->save();
    ↓
4. Observer::saving() فراخوانی می‌شود
    ↓
5. چک می‌کنه: $invoice->exists ← قدیمی است
6. چک می‌کنه: $invoice->tax_rate !== null ← دارای نرخ است
    ↓
7. نرخ جدید از Settings نمی‌گیرد! ✅
    ↓
8. محاسبه مالیات با نرخ ذخیره شده (9%)
    ↓
9. Invoice ذخیره می‌شود با همان tax_rate = 9
```

---

## 📊 مثال عملی

### شرایط اولیه:
- **1 فروردین:** VAT Rate = 9%
- **15 خرداد:** VAT Rate تغییر به 12%

### Invoice #001 (1 فروردین):
```php
$invoice = CustomerInvoice::create([
    'subtotal' => 100000,
]);

// در دیتابیس ذخیره می‌شود:
// tax_rate: 9
// tax_amount: 9000
// total_amount: 109000
```

### Invoice #003 (20 خرداد - بعد از تغییر):
```php
$invoice = CustomerInvoice::create([
    'subtotal' => 100000,
]);

// در دیتابیس ذخیره می‌شود:
// tax_rate: 12  ← نرخ جدید
// tax_amount: 12000
// total_amount: 112000
```

### Edit Invoice #001 (1 تیر):
```php
$invoice = CustomerInvoice::find(1);
$invoice->subtotal = 120000;
$invoice->save();

// در دیتابیس ذخیره می‌شود:
// tax_rate: 9  ← همان نرخ قدیم ✅
// tax_amount: 10800  ← 120000 * 0.09
// total_amount: 130800
```

---

## 🛡️ محافظت‌های اضافی

### 1️⃣ معافیت مالیاتی:
```php
if ($customer->tax_exempt) {
    $invoice->tax_rate = 0;
    $invoice->tax_amount = 0;
}
```

### 2️⃣ VAT غیرفعال:
```php
if (!is_vat_enabled()) {
    return; // محاسبه نمی‌شود
}
```

### 3️⃣ نرخ سفارشی در Item:
```php
foreach ($invoice->items as $item) {
    // ⭐ اولویت 1: نرخ آیتم
    // ⭐ اولویت 2: نرخ Invoice
    // ⭐ اولویت 3: نرخ Settings
    $taxRate = $item->tax_rate ?? $invoice->tax_rate ?? TaxCalculator::getVATRate();
}
```

---

## ⚖️ مزایا

### ✅ صحت حسابداری:
- Invoice های قدیمی با نرخ **زمان صدور** حساب می‌شوند
- گزارش‌های مالیاتی دقیق

### ✅ Audit Trail:
- نرخ مالیات در هر Invoice ذخیره شده
- می‌توان History کامل را پیگیری کرد

### ✅ انعطاف‌پذیری:
- نرخ‌های مختلف برای Invoice های مختلف
- پشتیبانی از تغییرات قانونی

### ✅ گزارش‌دهی:
```sql
-- تعداد Invoice با هر نرخ
SELECT tax_rate, COUNT(*) as count
FROM customer_invoices
GROUP BY tax_rate;

-- 9%  → 120 فاکتور
-- 12% → 45 فاکتور
```

---

## 📋 Database Schema

```sql
customer_invoices:
  - id
  - subtotal
  - tax_rate      ← ⭐ نرخ ذخیره شده
  - tax_method    ← ⭐ روش محاسبه
  - tax_amount
  - total_amount
  - created_at

supplier_invoices:
  - id
  - subtotal
  - tax_rate      ← ⭐ نرخ ذخیره شده
  - tax_method    ← ⭐ روش محاسبه
  - tax_amount
  - total_amount
  - created_at

supplier_invoice_items:
  - id
  - product_id
  - quantity
  - unit_price
  - tax_rate      ← ⭐ نرخ هر آیتم
  - tax_amount
  - total_price
```

---

## 🔄 Migration Path برای داده‌های قدیمی

اگر Invoice های قدیمی **بدون** `tax_rate` داری:

```php
// Migration
use RMS\Core\Models\Setting;

$defaultRate = Setting::get('accounting.vat.rate', 9);

CustomerInvoice::whereNull('tax_rate')->update([
    'tax_rate' => $defaultRate,
    'tax_method' => 'exclusive',
]);

SupplierInvoice::whereNull('tax_rate')->update([
    'tax_rate' => $defaultRate,
    'tax_method' => 'exclusive',
]);
```

یا دستی:

```bash
php artisan tinker

> $defaultRate = 9;
> CustomerInvoice::whereNull('tax_rate')->update(['tax_rate' => $defaultRate]);
> SupplierInvoice::whereNull('tax_rate')->update(['tax_rate' => $defaultRate]);
```

---

## 🎯 تست

```php
// Test: تغییر نرخ نباید Invoice قدیمی رو تغییر بده
public function test_old_invoice_keeps_original_tax_rate()
{
    // 1. نرخ اولیه: 9%
    Setting::set('accounting.vat.rate', 9);
    
    $invoice = CustomerInvoice::create([
        'subtotal' => 100000,
    ]);
    
    $this->assertEquals(9, $invoice->tax_rate);
    $this->assertEquals(9000, $invoice->tax_amount);
    
    // 2. تغییر نرخ به 12%
    Setting::set('accounting.vat.rate', 12);
    
    // 3. Edit کردن Invoice قدیمی
    $invoice->subtotal = 120000;
    $invoice->save();
    
    // ⭐ باید از نرخ قدیم (9%) استفاده کنه
    $this->assertEquals(9, $invoice->tax_rate);      // ✅ همان 9%
    $this->assertEquals(10800, $invoice->tax_amount); // 120000 * 0.09
}
```

---

## 📝 نتیجه‌گیری

✅ **نرخ مالیات در زمان ایجاد Invoice ذخیره می‌شود**  
✅ **Invoice های قدیمی از نرخ ذخیره شده استفاده می‌کنند**  
✅ **تغییر Settings تأثیری روی Invoice های قدیمی ندارد**  
✅ **صحت حسابداری حفظ می‌شود**  
✅ **Audit Trail کامل**  

---

**تاریخ:** 2026-01-24  
**وضعیت:** ✅ پیاده‌سازی شده
