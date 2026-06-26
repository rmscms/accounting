# 📘 راهنمای استفاده - سیستم‌های جدید حسابداری

این راهنما نحوه استفاده از قابلیت‌های جدید هسته حسابداری را توضیح می‌دهد.

---

## 1️⃣ Credit Notes (اعتبار برگشتی / برگشت فروش)

### زمان استفاده:
- مشتری کالا را برگشت داد
- تخفیف بعد از فاکتور به مشتری دادید
- خطا در فاکتور فروش رخ داده

### نحوه استفاده (از Shop Package):

```php
use RMS\Accounting\Adapters\AccountingAdapter;

$adapter = app(AccountingAdapter::class);

// ایجاد Credit Note
$creditNote = $adapter->createCreditNote([
    'customer_id' => 123,
    'customer_invoice_id' => 456, // لینک به فاکتور اصلی (optional)
    'credit_date' => now(),
    'credit_type' => 'return', // return, discount, correction
    'reason' => 'مشتری کالا را برگشت داد',
    'items' => [
        [
            'product_name' => 'لپ‌تاپ Asus',
            'quantity' => 1,
            'price' => 25000000,
        ],
    ],
]);

// صادر کردن (وارد دفتر کل می‌شود)
$adapter->issueCreditNote($creditNote->id);

// اعمال به فاکتور (کاهش balance_due)
$adapter->applyCreditNoteToInvoice($creditNote->id, $invoiceId);
```

### ثبت در دفتر کل:
```
بدهکار: فروش             25,000,000
بدهکار: مالیات پرداختنی     2,250,000
بستانکار: دریافتنی        27,250,000
```

---

## 2️⃣ Debit Notes (یادداشت بدهکار / برگشت خرید)

### زمان استفاده:
- ما کالا را به تامین‌کننده برگشت دادیم
- تخفیف بعد از فاکتور از تامین‌کننده گرفتیم
- خطا در فاکتور خرید رخ داده

### نحوه استفاده (از Inventory Package):

```php
$adapter = app(AccountingAdapter::class);

// ایجاد Debit Note
$debitNote = $adapter->createDebitNote([
    'supplier_id' => 789,
    'supplier_invoice_id' => 456,
    'debit_date' => now(),
    'debit_type' => 'return',
    'reason' => 'کالای معیوب',
    'items' => [
        [
            'product_name' => 'هارد دیسک 1TB',
            'quantity' => 5,
            'price' => 2000000,
        ],
    ],
]);

// صادر کردن
$this->ledgerService->issueDebitNote($debitNote);
```

### ثبت در دفتر کل:
```
بدهکار: پرداختنی          10,000,000
بستانکار: خرید            10,000,000
```

---

## 3️⃣ Customer Refunds (بازگشت وجه به مشتری)

### زمان استفاده:
- بعد از صدور Credit Note، باید پول به مشتری برگردانیم

### نحوه استفاده:

```php
$refund = $adapter->processCustomerRefund([
    'customer_id' => 123,
    'credit_note_id' => $creditNote->id, // optional
    'amount' => 27250000,
    'refund_method' => 'bank_transfer', // cash, bank_transfer, cheque, online
    'bank_id' => 5,
    'reason' => 'بازگشت وجه بابت کالای معیوب',
]);
```

### ثبت در دفتر کل:
```
بدهکار: دریافتنی          27,250,000
بستانکار: بانک ملت       27,250,000
```

---

## 4️⃣ Customer Advances (پیش دریافت)

### زمان استفاده:
- مشتری قبل از صدور فاکتور پول پرداخت کرد
- قرارداد پیش‌پرداخت با مشتری بستید

### نحوه استفاده:

```php
// دریافت پیش دریافت
$advance = $adapter->receiveCustomerAdvance([
    'customer_id' => 123,
    'amount' => 50000000,
    'payment_method' => 'bank_transfer',
    'bank_id' => 5,
]);

// بعداً که فاکتور صادر شد، اعمال کن
$adapter->applyCustomerAdvanceToInvoice(
    $advance->id,
    $invoiceId,
    50000000
);
```

### ثبت در دفتر کل (دریافت):
```
بدهکار: بانک ملت         50,000,000
بستانکار: پیش دریافت     50,000,000  (Liability)
```

### ثبت در دفتر کل (اعمال):
```
بدهکار: پیش دریافت       50,000,000
بستانکار: دریافتنی       50,000,000
```

---

## 5️⃣ Supplier Advances (پیش پرداخت)

### زمان استفاده:
- قبل از دریافت کالا، باید به تامین‌کننده پرداخت کنیم
- قرارداد پیش‌پرداخت با تامین‌کننده بستیم

### نحوه استفاده:

```php
// پرداخت پیش پرداخت
$advance = $adapter->paySupplierAdvance([
    'supplier_id' => 789,
    'amount' => 100000000,
    'payment_method' => 'bank_transfer',
    'bank_id' => 5,
]);
```

### ثبت در دفتر کل:
```
بدهکار: پیش پرداخت       100,000,000  (Asset)
بستانکار: بانک ملت       100,000,000
```

---

## 6️⃣ Accruals (تعهدات)

### انواع:

#### A. Accrued Revenue (درآمد تعهدی)
```php
use RMS\Accounting\Services\AccrualService;

$accrualService = app(AccrualService::class);

$accrual = $accrualService->createAccrual([
    'accrual_type' => 'accrued_revenue',
    'amount' => 10000000,
    'account_id' => $revenueAccountId,
    'description' => 'درآمد تعهدی بابت خدمات ارائه شده',
]);
```
ثبت:
```
بدهکار: دریافتنی          10,000,000
بستانکار: درآمد خدمات     10,000,000
```

#### B. Accrued Expense (هزینه تعهدی)
```php
$accrual = $accrualService->createAccrual([
    'accrual_type' => 'accrued_expense',
    'amount' => 5000000,
    'account_id' => $salaryExpenseAccountId,
    'description' => 'حقوق پایان ماه',
]);
```
ثبت:
```
بدهکار: هزینه حقوق        5,000,000
بستانکار: پرداختنی        5,000,000
```

---

## 7️⃣ Bad Debt (مطالبات مشکوک)

### A. ذخیره مطالبات مشکوک

```php
use RMS\Accounting\Services\BadDebtService;

$badDebtService = app(BadDebtService::class);

// محاسبه خودکار (Aging Analysis)
$provisionAmount = $badDebtService->calculateProvision([
    'method' => 'aging_analysis',
]);

// ثبت ذخیره
$provision = $badDebtService->recordProvision([
    'provision_amount' => $provisionAmount,
    'calculation_method' => 'aging_analysis',
]);
```

ثبت:
```
بدهکار: هزینه مطالبات مشکوک    5,000,000
بستانکار: ذخیره مطالبات مشکوک  5,000,000
```

### B. حذف مطالبات (Write-off)

```php
$writeoff = $badDebtService->writeOffBadDebt([
    'customer_id' => 123,
    'customer_invoice_id' => 456,
    'writeoff_amount' => 5000000,
    'reason' => 'ورشکستگی مشتری',
]);
```

ثبت:
```
بدهکار: ذخیره مطالبات مشکوک  5,000,000
بستانکار: دریافتنی            5,000,000
```

---

## 🔗 API Integration Examples

### مثال: Shop Package

```php
namespace App\Services;

use RMS\Accounting\Adapters\AccountingAdapter;

class OrderService
{
    protected AccountingAdapter $accounting;

    public function __construct(AccountingAdapter $accounting)
    {
        $this->accounting = $accounting;
    }

    public function processReturn($orderId)
    {
        $order = Order::find($orderId);
        
        // 1. ایجاد Credit Note
        $creditNote = $this->accounting->createCreditNote([
            'customer_id' => $order->customer_id,
            'customer_invoice_id' => $order->invoice_id,
            'credit_type' => 'return',
            'items' => $order->items->map(fn($item) => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->toArray(),
        ]);
        
        // 2. صادر کردن
        $this->accounting->issueCreditNote($creditNote->id);
        
        // 3. بازگشت وجه
        $refund = $this->accounting->processCustomerRefund([
            'customer_id' => $order->customer_id,
            'credit_note_id' => $creditNote->id,
            'amount' => $order->total,
            'refund_method' => 'bank_transfer',
        ]);
        
        return $refund;
    }
}
```

---

## 📋 Checklist: سناریوی کامل مشتری

```
✅ فاکتور فروش        → CustomerInvoiceService::createInvoice()
✅ پرداخت             → CustomerPaymentService::recordPayment()
✅ برگشت کالا         → CreditNoteService::createCreditNote()
✅ بازگشت وجه         → RefundService::processCustomerRefund()
✅ پیش دریافت         → AdvancePaymentService::receiveCustomerAdvance()
✅ اعمال پیش دریافت   → AdvancePaymentService::applyCustomerAdvanceToInvoice()
✅ چک پرداخت          → ChequeService (قبلاً موجود)
✅ چک برگشتی          → ChequeService::bounceCheck()
✅ مطالبات مشکوک      → BadDebtService::writeOffBadDebt()
```

---

## 🔧 Configuration

### config/accounting.php

```php
return [
    // Customer Advance Account (Liability)
    'customer_advance_account_id' => 2010,
    
    // Supplier Advance Account (Asset)
    'supplier_advance_account_id' => 1050,
    
    // Bad Debt Expense Account
    'bad_debt_expense_account_id' => 6120,
    
    // Allowance for Doubtful Accounts (Contra Asset)
    'allowance_doubtful_accounts_id' => 1030,
];
```

---

## ✅ تست اتصال

```php
use RMS\Accounting\Adapters\AccountingAdapter;

$adapter = app(AccountingAdapter::class);

// Health Check
$status = $adapter->healthCheck();

dd($status);
// [
//   'status' => 'ok',
//   'mode' => 'local',  // یا 'remote'
//   'services_loaded' => [...],
// ]
```

---

## 🎯 نکات مهم

1. **همیشه از Adapter استفاده کنید** - برای flexibility
2. **Credit Note قبل از Refund** - ابتدا سند حسابداری، بعد پرداخت
3. **Advance را اعمال کنید** - وقتی فاکتور صادر شد
4. **Aging Analysis هر ماه** - برای مطالبات مشکوک
5. **Double-Entry همیشه** - همه تراکنش‌ها دوطرفه ثبت می‌شوند

---

**📚 برای اطلاعات بیشتر:**
- `IMPLEMENTATION_COMPLETE.md` - گزارش کامل
- `INTEGRATION_GUIDE.md` - راهنمای Integration
- `COMPLETE_DEVELOPMENT_PLAN.md` - پلن توسعه
