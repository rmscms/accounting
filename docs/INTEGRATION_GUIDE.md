# 🔗 راهنمای Integration با پکیج Accounting

## 🎯 دو سناریو اتصال

### سناریو 1: پکیج نصب شده (Same Server) ⭐ **توصیه می‌شود**
### سناریو 2: سرور جداگانه (Microservice)

---

## 📦 سناریو 1: پکیج نصب شده (Direct Integration)

### ✅ روش صحیح: استفاده مستقیم از Services

اگه پکیج `rmscms/accounting` روی همون سرور نصب شده، **مستقیم از Service ها استفاده کن!**

**چرا؟**
- ✅ سریع‌تر (بدون HTTP Overhead)
- ✅ Type-Safe (خطاهای PHP در Compile Time)
- ✅ Transaction Support (DB Transaction یکپارچه)
- ✅ بدون نیاز به Authentication
- ✅ استفاده از Dependency Injection

---

### 🔧 نحوه استفاده مستقیم:

#### 1️⃣ **ثبت فاکتور فروش از Shop:**

```php
namespace App\Shop\Services;

use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\TaxService;

class OrderService
{
    protected CustomerInvoiceService $invoiceService;
    protected TaxService $taxService;

    public function __construct(
        CustomerInvoiceService $invoiceService,
        TaxService $taxService
    ) {
        $this->invoiceService = $invoiceService;
        $this->taxService = $taxService;
    }

    /**
     * ثبت سفارش در حسابداری
     */
    public function createOrderInvoice(Order $order)
    {
        // ایجاد فاکتور حسابداری
        $invoice = $this->invoiceService->createInvoice([
            'customer_id' => $order->customer_id,
            'invoice_date' => $order->created_at,
            'due_date' => $order->created_at->addDays(30),
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount,
            'currency_code' => 'IRR',
            'notes' => "سفارش شماره {$order->order_number}",
        ]);

        // محاسبه و اعمال مالیات
        $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);
        $invoice->save();

        // ذخیره ID فاکتور در سفارش
        $order->update([
            'accounting_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }
}
```

#### 2️⃣ **ثبت دریافت از مشتری:**

```php
use RMS\Accounting\Services\CustomerPaymentService;

class PaymentService
{
    protected CustomerPaymentService $paymentService;

    public function __construct(CustomerPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function recordPayment(Order $order, Payment $payment)
    {
        $accountingPayment = $this->paymentService->recordPayment([
            'customer_invoice_id' => $order->accounting_invoice_id,
            'amount' => $payment->amount,
            'payment_date' => $payment->paid_at,
            'payment_method_id' => $this->getPaymentMethodId($payment->method),
            'reference_number' => $payment->transaction_id,
            'notes' => "پرداخت سفارش {$order->order_number}",
        ]);

        return $accountingPayment;
    }
}
```

#### 3️⃣ **ثبت COGS از Inventory:**

```php
use RMS\Accounting\Services\COGSService;

class StockService
{
    protected COGSService $cogsService;

    public function __construct(COGSService $cogsService)
    {
        $this->cogsService = $cogsService;
    }

    public function recordSaleCOGS(Sale $sale)
    {
        foreach ($sale->items as $item) {
            $this->cogsService->recordCOGS([
                'product_id' => $item->product_id,
                'product_sku' => $item->product->sku,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'cost_per_unit' => $item->product->average_cost,
                'total_cost' => $item->quantity * $item->product->average_cost,
                'sale_date' => $sale->created_at,
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
            ]);
        }
    }
}
```

#### 4️⃣ **دریافت مانده مشتری:**

```php
use RMS\Accounting\Models\Customer;

class CustomerController extends Controller
{
    public function show($id)
    {
        $customer = Customer::with(['invoices', 'payments'])->findOrFail($id);
        
        // محاسبه مانده
        $balance = $customer->invoices->sum('balance_due');
        
        return view('customers.show', [
            'customer' => $customer,
            'balance' => $balance,
        ]);
    }
}
```

---

## 🔄 ساخت Adapter Pattern (توصیه می‌شود)

برای اینکه کد Shop/Inventory به پکیج Accounting وابسته نباشه، یه **Adapter** بساز:

### ✅ **AccountingAdapter** (Bridge Layer)

```php
namespace App\Adapters;

use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\CustomerPaymentService;
use RMS\Accounting\Services\COGSService;
use Illuminate\Support\Facades\Http;

class AccountingAdapter
{
    protected ?CustomerInvoiceService $invoiceService;
    protected ?CustomerPaymentService $paymentService;
    protected ?COGSService $cogsService;
    protected bool $useRemoteApi;

    public function __construct()
    {
        // تشخیص اینکه پکیج نصب شده یا نه
        $this->useRemoteApi = config('accounting.use_remote_api', false);
        
        if (!$this->useRemoteApi) {
            // پکیج نصب شده، استفاده مستقیم
            $this->invoiceService = app(CustomerInvoiceService::class);
            $this->paymentService = app(CustomerPaymentService::class);
            $this->cogsService = app(COGSService::class);
        }
    }

    /**
     * ثبت فاکتور فروش
     */
    public function recordSalesInvoice(array $data)
    {
        if ($this->useRemoteApi) {
            // استفاده از API
            return $this->callRemoteApi('POST', '/sales/record-invoice', $data);
        }
        
        // استفاده مستقیم از Service
        $invoice = $this->invoiceService->createInvoice($data);
        return $invoice;
    }

    /**
     * ثبت دریافت
     */
    public function recordPayment(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/record-payment', $data);
        }
        
        return $this->paymentService->recordPayment($data);
    }

    /**
     * ثبت COGS
     */
    public function recordCOGS(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/inventory/record-cogs', $data);
        }
        
        return $this->cogsService->recordCOGS($data);
    }

    /**
     * دریافت مانده مشتری
     */
    public function getCustomerBalance(int $customerId)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/customers/{$customerId}/balance");
        }
        
        $customer = \RMS\Accounting\Models\Customer::find($customerId);
        return [
            'customer_id' => $customerId,
            'balance' => $customer->invoices->sum('balance_due'),
            'currency' => 'IRR',
        ];
    }

    /**
     * فراخوانی Remote API
     */
    protected function callRemoteApi(string $method, string $endpoint, array $data = [])
    {
        $baseUrl = config('accounting.api_base_url');
        $apiKey = config('accounting.api_key');
        
        $response = Http::withToken($apiKey)
            ->{strtolower($method)}($baseUrl . '/api/service/accounting' . $endpoint, $data);
        
        if ($response->failed()) {
            throw new \Exception('Accounting API Error: ' . $response->body());
        }
        
        return $response->json();
    }
}
```

---

### 📝 Config فایل:

```php
// config/accounting.php

return [
    /*
    |--------------------------------------------------------------------------
    | Integration Mode
    |--------------------------------------------------------------------------
    |
    | اگه پکیج accounting روی همین سرور نصب شده: false
    | اگه روی سرور جداگانه است (Microservice): true
    |
    */
    'use_remote_api' => env('ACCOUNTING_USE_REMOTE_API', false),

    /*
    |--------------------------------------------------------------------------
    | Remote API Configuration (فقط برای Microservice)
    |--------------------------------------------------------------------------
    */
    'api_base_url' => env('ACCOUNTING_API_URL', 'http://accounting-service.local'),
    'api_key' => env('ACCOUNTING_API_KEY', ''),
];
```

---

### 🎯 استفاده از Adapter:

```php
namespace App\Shop\Services;

use App\Adapters\AccountingAdapter;

class OrderService
{
    protected AccountingAdapter $accounting;

    public function __construct(AccountingAdapter $accounting)
    {
        $this->accounting = $accounting;
    }

    public function createOrder(array $data)
    {
        // ... ساخت Order ...

        // ثبت در حسابداری (بدون اینکه بدونیم Local یا Remote)
        $invoice = $this->accounting->recordSalesInvoice([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'items' => $order->items->toArray(),
            'total_amount' => $order->total,
        ]);

        $order->accounting_invoice_id = $invoice['id'] ?? $invoice->id;
        $order->save();

        return $order;
    }
}
```

---

## 🌐 سناریو 2: سرور جداگانه (Microservice)

### زمان استفاده:

- ✅ Accounting روی سرور جدا
- ✅ چند پروژه به یک Accounting متصل هستند
- ✅ نیاز به Scale مستقل
- ✅ تیم‌های جدا کار می‌کنند

### ⚠️ معایب:

- ❌ کندتر (HTTP Overhead)
- ❌ نیاز به Authentication
- ❌ Transaction یکپارچه نیست
- ❌ Error Handling پیچیده‌تر

### ✅ نحوه استفاده:

```php
// از همون Adapter استفاده کن، فقط config رو تغییر بده:

// .env
ACCOUNTING_USE_REMOTE_API=true
ACCOUNTING_API_URL=http://accounting-api.company.com
ACCOUNTING_API_KEY=your-secret-key
```

---

## 📊 مقایسه دو روش:

| ویژگی | Local (Direct) | Remote (API) |
|-------|---------------|--------------|
| **سرعت** | ⚡⚡⚡ خیلی سریع | 🐢 کند (HTTP) |
| **Type Safety** | ✅ PHP Type Hints | ❌ JSON |
| **Transaction** | ✅ یکپارچه | ❌ Distributed |
| **Authentication** | ✅ ندارد | ⚠️ نیاز به API Key |
| **Error Handling** | ✅ Exception | ⚠️ HTTP Status |
| **Deployment** | ✅ یک سرور | ⚠️ چند سرور |
| **Scalability** | ⚠️ محدود | ✅ مستقل |
| **Team Independence** | ❌ وابسته | ✅ مستقل |

---

## 🎯 توصیه نهایی:

### ⭐ **استفاده مستقیم از Services (Local)**

اگه Shop و Accounting روی همون سرور هستند:
```php
// ✅ استفاده مستقیم از Service
use RMS\Accounting\Services\CustomerInvoiceService;

$invoice = app(CustomerInvoiceService::class)->createInvoice([...]);
```

### 🌉 **استفاده از Adapter (توصیه می‌شود)**

برای انعطاف‌پذیری آینده:
```php
// ✅ استفاده از Adapter
use App\Adapters\AccountingAdapter;

$invoice = app(AccountingAdapter::class)->recordSalesInvoice([...]);
```

این روش بهترینه چون:
- ✅ در حال حاضر از Service مستقیم استفاده می‌کنه (سریع)
- ✅ در آینده می‌تونی به Microservice تبدیلش کنی (فقط config)
- ✅ کد Shop به Accounting وابسته نیست
- ✅ Test کردن راحت‌تره (Mock)

---

## 📝 مثال کامل پروژه:

```
shop/
├── app/
│   ├── Adapters/
│   │   └── AccountingAdapter.php    ← Bridge
│   ├── Services/
│   │   ├── OrderService.php         ← استفاده از Adapter
│   │   └── PaymentService.php       ← استفاده از Adapter
│   └── Models/
│       └── Order.php
├── config/
│   └── accounting.php                ← تنظیمات Integration
└── composer.json
    └── require: {
        "rmscms/accounting": "^1.0"   ← پکیج نصب شده
    }
```

---

## 🔧 Setup کامل:

### 1️⃣ نصب پکیج:

```bash
composer require rmscms/accounting
```

### 2️⃣ ساخت Adapter:

```bash
php artisan make:class Adapters/AccountingAdapter
```

### 3️⃣ Config:

```php
// config/accounting.php
return [
    'use_remote_api' => false, // ⭐ Local mode
];
```

### 4️⃣ استفاده:

```php
use App\Adapters\AccountingAdapter;

$accounting = app(AccountingAdapter::class);
$invoice = $accounting->recordSalesInvoice([...]);
```

---

## ✅ خلاصه:

| شرایط | روش پیشنهادی | دلیل |
|-------|-------------|------|
| پکیج نصب شده | **Direct Service** | سریع، ساده، Type-Safe |
| نیاز به انعطاف | **Adapter Pattern** | آینده‌نگر، Test-friendly |
| سرور جدا | **Remote API** | Microservice، Scale مستقل |

**توصیه: Adapter Pattern ⭐**

---

**تاریخ:** 2026-01-24  
**نسخه:** 1.0
