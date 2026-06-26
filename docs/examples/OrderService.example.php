<?php

namespace App\Shop\Services;

use RMS\Accounting\Adapters\AccountingAdapter;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Service با Integration به Accounting
 * 
 * مثال واقعی از نحوه استفاده از AccountingAdapter
 */
class OrderService
{
    protected AccountingAdapter $accounting;

    public function __construct(AccountingAdapter $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * ایجاد سفارش جدید + ثبت در حسابداری
     */
    public function createOrder(array $orderData): Order
    {
        DB::beginTransaction();
        
        try {
            // 1. ایجاد سفارش در Shop
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $orderData['customer_id'],
                'subtotal' => $orderData['subtotal'],
                'discount' => $orderData['discount'] ?? 0,
                'total' => $orderData['total'],
                'status' => 'pending',
            ]);
            
            // ایجاد آیتم‌های سفارش
            foreach ($orderData['items'] as $item) {
                $order->items()->create($item);
            }
            
            // 2. ثبت فاکتور در حسابداری
            try {
                $invoice = $this->accounting->recordSalesInvoice([
                    'customer_id' => $order->customer_id,
                    'invoice_date' => now(),
                    'due_date' => now()->addDays(30),
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'subtotal' => $order->subtotal,
                    'discount_amount' => $order->discount,
                    'apply_tax' => true, // محاسبه خودکار مالیات
                    'currency_code' => 'IRR',
                    'notes' => "سفارش شماره {$order->order_number}",
                ]);
                
                // ذخیره لینک به فاکتور حسابداری
                $order->update([
                    'accounting_invoice_id' => is_array($invoice) ? $invoice['id'] : $invoice->id,
                ]);
                
                Log::info('Order invoice recorded in accounting', [
                    'order_id' => $order->id,
                    'invoice_id' => $order->accounting_invoice_id,
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to record invoice in accounting', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                
                // می‌تونی تصمیم بگیری که rollback کنی یا فقط log کنی
                // اینجا فقط log می‌کنیم و سفارش ثبت می‌شه
            }
            
            DB::commit();
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت پرداخت + ثبت در حسابداری
     */
    public function recordPayment(Order $order, array $paymentData): Payment
    {
        DB::beginTransaction();
        
        try {
            // 1. ثبت پرداخت در Shop
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['method'],
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'status' => 'completed',
                'paid_at' => now(),
            ]);
            
            // آپدیت وضعیت سفارش
            $totalPaid = $order->payments()->sum('amount');
            if ($totalPaid >= $order->total) {
                $order->update(['payment_status' => 'paid']);
            } else {
                $order->update(['payment_status' => 'partially_paid']);
            }
            
            // 2. ثبت دریافت در حسابداری
            if ($order->accounting_invoice_id) {
                try {
                    $accountingPayment = $this->accounting->recordCustomerPayment([
                        'customer_invoice_id' => $order->accounting_invoice_id,
                        'amount' => $payment->amount,
                        'payment_date' => $payment->paid_at,
                        'payment_method_id' => $this->mapPaymentMethod($payment->payment_method),
                        'reference_number' => $payment->transaction_id,
                        'notes' => "پرداخت سفارش {$order->order_number}",
                    ]);
                    
                    $payment->update([
                        'accounting_payment_id' => is_array($accountingPayment) 
                            ? $accountingPayment['id'] 
                            : $accountingPayment->id,
                    ]);
                    
                    Log::info('Payment recorded in accounting', [
                        'payment_id' => $payment->id,
                        'accounting_payment_id' => $payment->accounting_payment_id,
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to record payment in accounting', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            DB::commit();
            return $payment;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * لغو سفارش + برگشت سند در حسابداری
     */
    public function cancelOrder(Order $order, string $reason): void
    {
        DB::beginTransaction();
        
        try {
            // آپدیت وضعیت سفارش
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            
            // اگه در حسابداری ثبت شده، باید برگشت بخوره
            if ($order->accounting_invoice_id) {
                // ⚠️ این قسمت نیاز به API endpoint برای برگشت سند داره
                // فعلاً فقط log می‌کنیم
                Log::warning('Order cancelled - accounting invoice needs reversal', [
                    'order_id' => $order->id,
                    'invoice_id' => $order->accounting_invoice_id,
                ]);
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * دریافت مانده مشتری
     */
    public function getCustomerBalance(int $customerId): array
    {
        try {
            return $this->accounting->getCustomerBalance($customerId);
        } catch (\Exception $e) {
            Log::error('Failed to get customer balance', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'customer_id' => $customerId,
                'balance' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * تبدیل payment method به accounting payment_method_id
     */
    protected function mapPaymentMethod(string $method): int
    {
        // نگاشت روش‌های پرداخت Shop به IDs حسابداری
        $mapping = [
            'cash' => 1,
            'card' => 2,
            'online' => 3,
            'pos' => 4,
            'transfer' => 5,
        ];
        
        return $mapping[$method] ?? 1; // پیش‌فرض: نقدی
    }

    /**
     * تولید شماره سفارش
     */
    protected function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
