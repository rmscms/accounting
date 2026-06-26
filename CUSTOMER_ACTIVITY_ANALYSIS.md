# 📊 بررسی جامع: گردش حساب مشتری و فرآیندهای مالی

## ✅ چیزهایی که **داریم**

### 1️⃣ **فاکتور ثبت کرده** ✅
```php
CustomerInvoice::create([...])

// Status:
- draft (پیش‌نویس)
- issued (صادر شده)
- cancelled (لغو شده)
- void (باطل شده)

// Payment Status:
- unpaid (پرداخت نشده)
- partially_paid (پرداخت جزئی)
- paid (پرداخت کامل)
```

### 2️⃣ **پول داده (دریافت از مشتری)** ✅
```php
CustomerPayment::create([...])

// ویژگی‌ها:
- لینک به فاکتور (customer_invoice_id)
- مبلغ پرداخت
- روش پرداخت (cash, card, online, pos, transfer)
- تاریخ پرداخت
- شماره مرجع
```

### 3️⃣ **پرداخت نقدی** ✅
```php
CustomerPayment where payment_method_id = 1 // Cash

// روش‌های پرداخت موجود:
- نقدی (Cash)
- کارت (Card)
- آنلاین (Online)
- POS
- انتقال بانکی (Transfer)
- چک (Cheque)
```

### 4️⃣ **چک داده** ✅
```php
Cheque::create([
    'cheque_type' => 'received', // دریافتی
    'status' => 'pending',
    'amount' => 1000000,
    'due_date' => '2026-02-01',
])

// Status های چک:
- issued (صادر شده)
- pending (در انتظار)
- cashed (نقد شده) ✅
- bounced (برگشتی) ✅
- cancelled (لغو شده)
```

### 5️⃣ **چک برگشت خورده** ✅
```php
$cheque->update([
    'status' => 'bounced',
    'bounced_at' => now(),
    'bounce_reason' => 'کسری موجودی',
]);
```

### 6️⃣ **چک نقد شده** ✅
```php
$cheque->update([
    'status' => 'cashed',
    'cashed_at' => now(),
]);
```

### 7️⃣ **گردش حساب مشتری** ✅
```php
ReportService::getCustomerStatement([
    'customer_id' => 123,
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
]);

// نتیجه:
[
    'invoices' => [...],  // تمام فاکتورها
    'payments' => [...],  // تمام پرداخت‌ها
    'summary' => [
        'total_invoices' => 10000000,
        'total_payments' => 6000000,
        'balance' => 4000000,
    ]
]
```

### 8️⃣ **اسناد قابل برگشت** ✅
```php
DocumentService::reverseDocument($documentId, $reason);

// امکانات:
- برگشت سند (Reversal Entry)
- ایجاد سند معکوس
- Audit Trail کامل
- Immutable Ledger
```

---

## ❌ چیزهایی که **نداریم**

### 1️⃣ **برگشت کالا / اعتبار (Credit Note)** ❌

**مشکل:** مدل و سیستم Credit Note نداریم!

**نیاز:**
- Model: `CreditNote`
- Service: `CreditNoteService`
- لینک به فاکتور اصلی
- کسر از مانده مشتری
- ثبت در دفتر کل

---

### 2️⃣ **پس دادن پول (Refund)** ❌

**مشکل:** سیستم Refund مجزا نداریم!

**نیاز:**
- Model: `CustomerRefund`
- لینک به پرداخت یا Credit Note
- ثبت در دفتر کل
- کاهش از مانده مشتری

---

### 3️⃣ **تاریخچه کامل Timeline** ⚠️ نیمه‌کامل

**موجود:**
- فاکتورها
- پرداخت‌ها
- چک‌ها

**نیاز:**
- Timeline یکپارچه با همه رویدادها
- مرتب‌سازی زمانی
- نمایش تغییرات وضعیت

---

## 🔧 راه‌حل پیشنهادی

### مرحله 1: ساخت Credit Note System

#### 1️⃣ **Model: CreditNote**
```php
class CreditNote extends Model
{
    protected $fillable = [
        'credit_note_number',
        'customer_id',
        'customer_invoice_id',  // لینک به فاکتور اصلی
        'credit_date',
        'reason',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency_code',
        'status', // draft, issued, applied, void
        'applied_to_invoice_id', // اعمال شده به کدام فاکتور
        'notes',
    ];
    
    // Relations
    public function customer() { ... }
    public function originalInvoice() { ... }
    public function appliedToInvoice() { ... }
    public function items() { ... }
}
```

#### 2️⃣ **Model: CreditNoteItem**
```php
class CreditNoteItem extends Model
{
    protected $fillable = [
        'credit_note_id',
        'product_id',
        'product_name',
        'quantity',
        'price',
        'tax_rate',
        'tax_amount',
        'total',
    ];
}
```

#### 3️⃣ **Service: CreditNoteService**
```php
class CreditNoteService
{
    /**
     * ایجاد اعتبار برگشتی
     */
    public function createCreditNote(array $data)
    {
        DB::beginTransaction();
        
        try {
            // 1. ایجاد Credit Note
            $creditNote = CreditNote::create($data);
            
            // 2. ایجاد آیتم‌ها
            foreach ($data['items'] as $item) {
                $creditNote->items()->create($item);
            }
            
            // 3. محاسبه مالیات
            $creditNote = $this->taxService->applyVAT($creditNote);
            
            // 4. کسر از فاکتور اصلی
            if ($creditNote->customer_invoice_id) {
                $this->applyToInvoice($creditNote);
            }
            
            // 5. ثبت در دفتر کل
            $this->recordInLedger($creditNote);
            
            DB::commit();
            return $creditNote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * اعمال به فاکتور
     */
    protected function applyToInvoice(CreditNote $creditNote)
    {
        $invoice = CustomerInvoice::find($creditNote->customer_invoice_id);
        
        // کسر از مانده فاکتور
        $invoice->balance_due -= $creditNote->total_amount;
        
        // آپدیت وضعیت پرداخت
        if ($invoice->balance_due <= 0) {
            $invoice->payment_status = 'paid';
        }
        
        $invoice->save();
        
        // لینک Credit Note به فاکتور
        $creditNote->update(['applied_to_invoice_id' => $invoice->id]);
    }
    
    /**
     * ثبت در دفتر کل
     */
    protected function recordInLedger(CreditNote $creditNote)
    {
        $this->ledgerService->recordTransaction([
            'document_type' => 'credit_note',
            'reference_type' => 'credit_note',
            'reference_id' => $creditNote->id,
            'description' => "اعتبار برگشتی - {$creditNote->credit_note_number}",
        ], [
            // بدهکار: فروش (کاهش فروش)
            [
                'account_id' => $this->getSalesAccountId(),
                'debit_amount' => $creditNote->subtotal,
            ],
            // بدهکار: مالیات پرداختنی (کاهش مالیات)
            [
                'account_id' => $this->getTaxAccountId(),
                'debit_amount' => $creditNote->tax_amount,
            ],
            // بستانکار: دریافتنی از مشتری (کاهش دریافتنی)
            [
                'account_id' => $this->getARAccountId(),
                'credit_amount' => $creditNote->total_amount,
            ],
        ]);
    }
}
```

---

### مرحله 2: ساخت Refund System

#### 1️⃣ **Model: CustomerRefund**
```php
class CustomerRefund extends Model
{
    protected $fillable = [
        'refund_number',
        'customer_id',
        'credit_note_id',  // لینک به Credit Note
        'customer_payment_id', // پرداخت اصلی که بازگشت داده میشه
        'amount',
        'refund_date',
        'refund_method', // cash, transfer, cheque
        'status', // pending, processed, cancelled
        'reference_number',
        'notes',
    ];
    
    public function customer() { ... }
    public function creditNote() { ... }
    public function originalPayment() { ... }
}
```

#### 2️⃣ **Service: RefundService**
```php
class RefundService
{
    /**
     * پس دادن پول به مشتری
     */
    public function processRefund(array $data)
    {
        DB::beginTransaction();
        
        try {
            // 1. ایجاد Refund
            $refund = CustomerRefund::create($data);
            
            // 2. آپدیت Credit Note
            if ($refund->credit_note_id) {
                $creditNote = CreditNote::find($refund->credit_note_id);
                $creditNote->update(['status' => 'refunded']);
            }
            
            // 3. ثبت در دفتر کل
            $this->recordInLedger($refund);
            
            // 4. تغییر وضعیت به processed
            $refund->update(['status' => 'processed']);
            
            DB::commit();
            return $refund;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    protected function recordInLedger(CustomerRefund $refund)
    {
        $this->ledgerService->recordTransaction([
            'document_type' => 'refund',
            'reference_type' => 'refund',
            'reference_id' => $refund->id,
        ], [
            // بدهکار: دریافتنی از مشتری (افزایش دریافتنی - منفی!)
            [
                'account_id' => $this->getARAccountId(),
                'debit_amount' => $refund->amount,
            ],
            // بستانکار: بانک/صندوق (کاهش موجودی)
            [
                'account_id' => $this->getCashAccountId(),
                'credit_amount' => $refund->amount,
            ],
        ]);
    }
}
```

---

### مرحله 3: Timeline یکپارچه

#### **Service: CustomerActivityService**
```php
class CustomerActivityService
{
    /**
     * دریافت Timeline کامل مشتری
     */
    public function getCustomerTimeline(int $customerId, array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $activities = collect();
        
        // 1. فاکتورها
        $invoices = CustomerInvoice::where('customer_id', $customerId)
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(fn($invoice) => [
                'date' => $invoice->invoice_date,
                'type' => 'invoice',
                'type_label' => 'فاکتور فروش',
                'reference' => $invoice->invoice_number,
                'amount' => $invoice->total_amount,
                'debit' => $invoice->total_amount,
                'credit' => 0,
                'balance' => null,
                'status' => $invoice->payment_status,
                'icon' => 'ph-file-text',
                'color' => 'primary',
                'data' => $invoice,
            ]);
        
        // 2. پرداخت‌ها
        $payments = CustomerPayment::where('customer_id', $customerId)
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(fn($payment) => [
                'date' => $payment->payment_date,
                'type' => 'payment',
                'type_label' => 'دریافت وجه',
                'reference' => $payment->reference_number,
                'amount' => $payment->amount,
                'debit' => 0,
                'credit' => $payment->amount,
                'balance' => null,
                'status' => $payment->status,
                'icon' => 'ph-money',
                'color' => 'success',
                'data' => $payment,
            ]);
        
        // 3. Credit Notes
        $creditNotes = CreditNote::where('customer_id', $customerId)
            ->whereBetween('credit_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(fn($cn) => [
                'date' => $cn->credit_date,
                'type' => 'credit_note',
                'type_label' => 'اعتبار برگشتی',
                'reference' => $cn->credit_note_number,
                'amount' => $cn->total_amount,
                'debit' => 0,
                'credit' => $cn->total_amount,
                'balance' => null,
                'status' => $cn->status,
                'icon' => 'ph-arrow-u-up-left',
                'color' => 'warning',
                'data' => $cn,
            ]);
        
        // 4. Refunds
        $refunds = CustomerRefund::where('customer_id', $customerId)
            ->whereBetween('refund_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(fn($refund) => [
                'date' => $refund->refund_date,
                'type' => 'refund',
                'type_label' => 'بازگشت وجه',
                'reference' => $refund->refund_number,
                'amount' => $refund->amount,
                'debit' => $refund->amount,
                'credit' => 0,
                'balance' => null,
                'status' => $refund->status,
                'icon' => 'ph-arrow-bend-up-left',
                'color' => 'danger',
                'data' => $refund,
            ]);
        
        // 5. چک‌ها
        $cheques = Cheque::where('payer_name', 'LIKE', "%{$customerId}%") // نیاز به relation بهتر
            ->whereBetween('issue_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(fn($cheque) => [
                'date' => $cheque->issue_date,
                'type' => 'cheque',
                'type_label' => $cheque->status === 'cashed' ? 'چک نقد شد' : 
                               ($cheque->status === 'bounced' ? 'چک برگشت' : 'چک دریافتی'),
                'reference' => $cheque->cheque_number,
                'amount' => $cheque->amount,
                'debit' => $cheque->status === 'bounced' ? $cheque->amount : 0,
                'credit' => $cheque->status === 'cashed' ? $cheque->amount : 0,
                'balance' => null,
                'status' => $cheque->status,
                'icon' => $cheque->status === 'bounced' ? 'ph-warning' : 'ph-checks',
                'color' => $cheque->status === 'bounced' ? 'danger' : 
                          ($cheque->status === 'cashed' ? 'success' : 'info'),
                'data' => $cheque,
            ]);
        
        // ترکیب همه
        $activities = $activities
            ->merge($invoices)
            ->merge($payments)
            ->merge($creditNotes)
            ->merge($refunds)
            ->merge($cheques)
            ->sortBy('date');
        
        // محاسبه مانده
        $balance = 0;
        $activities = $activities->map(function($activity) use (&$balance) {
            $balance += $activity['debit'] - $activity['credit'];
            $activity['balance'] = $balance;
            return $activity;
        });
        
        return [
            'customer_id' => $customerId,
            'period' => $dateRange,
            'activities' => $activities->values()->toArray(),
            'summary' => [
                'opening_balance' => 0,
                'total_invoices' => $invoices->sum('amount'),
                'total_payments' => $payments->sum('amount'),
                'total_credit_notes' => $creditNotes->sum('amount'),
                'total_refunds' => $refunds->sum('amount'),
                'closing_balance' => $balance,
            ],
        ];
    }
}
```

---

## 📊 خلاصه وضعیت

| فرآیند | وضعیت | توضیح |
|--------|-------|-------|
| فاکتور ثبت | ✅ | CustomerInvoice |
| پول داده | ✅ | CustomerPayment |
| **برگشت کالا** | ❌ | نیاز به CreditNote |
| **پس دادن پول** | ❌ | نیاز به Refund |
| پرداخت نقد | ✅ | PaymentMethod |
| چک داده | ✅ | Cheque |
| چک برگشت | ✅ | Cheque::bounced |
| چک نقد شده | ✅ | Cheque::cashed |
| گردش حساب | ✅ | CustomerStatement |
| **Timeline کامل** | ⚠️ | نیاز به بهبود |

---

## 🎯 اقدامات پیشنهادی:

### فاز 1: Credit Note System (اولویت بالا)
1. ✅ Migration: credit_notes & credit_note_items
2. ✅ Model: CreditNote, CreditNoteItem
3. ✅ Service: CreditNoteService
4. ✅ Controller: CreditNotesController
5. ✅ Routes & Views

### فاز 2: Refund System
1. ✅ Migration: customer_refunds
2. ✅ Model: CustomerRefund
3. ✅ Service: RefundService
4. ✅ Controller: RefundsController

### فاز 3: Timeline یکپارچه
1. ✅ Service: CustomerActivityService
2. ✅ View: customer-timeline.blade.php
3. ✅ API: Timeline Endpoint

---

**تاریخ:** 2026-01-24  
**وضعیت:** نیاز به توسعه Credit Note & Refund System
