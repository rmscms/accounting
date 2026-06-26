<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class SupplierPaymentsSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // چک کردن پرداخت‌های موجود برای این ماه
        $monthPrefix = 'SPY-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existingCount = DB::table('supplier_payments')
            ->where('payment_number', 'LIKE', $monthPrefix . '%')
            ->count();
        
        if ($existingCount > 0) {
            $this->command->info("    💸 پرداخت‌ها: {$existingCount} پرداخت موجود (skip)");
            return ['payments' => $existingCount];
        }
        
        // گرفتن فاکتورهای unpaid یا partially_paid تامین‌کننده
        $unpaidInvoices = DB::table('supplier_invoices')
            ->where(function($query) {
                $query->where('payment_status', 'unpaid')
                      ->orWhere('payment_status', 'partially_paid');
            })
            ->where('balance_due', '>', 0)
            ->limit(250) // حداکثر 250 فاکتور در ماه
            ->get();
        
        if ($unpaidInvoices->isEmpty()) {
            $this->command->info("    💸 پرداخت‌ها: 0 مورد (فاکتور unpaid وجود ندارد)");
            return ['payments' => 0];
        }
        
        $payments = [];
        $paymentCount = 0;
        $totalPaidAmount = 0;
        
        // دریافت payment methods موجود
        $paymentMethods = DB::table('payment_methods')->pluck('id')->toArray();
        if (empty($paymentMethods)) {
            $this->command->warn("    ⚠️  هیچ روش پرداختی یافت نشد!");
            return ['payments' => 0];
        }
        
        foreach ($unpaidInvoices as $invoice) {
            // 60% احتمال پرداخت کامل، 40% پرداخت جزئی (کمتر از مشتریان)
            $paymentAmount = rand(0, 10) > 4 
                ? $invoice->balance_due 
                : $invoice->balance_due * (rand(40, 80) / 100);
            
            $paymentDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            
            $paymentCount++;
            $totalPaidAmount += $paymentAmount;
            
            // انتخاب روش پرداخت و تعیین bank/cashbox
            $paymentMethodId = $paymentMethods[array_rand($paymentMethods)];
            $bankId = null;
            $cashBoxId = null;
            $chequeId = null;
            
            // تصمیم‌گیری براساس نوع روش پرداخت
            $rand = rand(1, 100);
            if ($rand <= 40) {
                // 40% بانکی
                $banks = DB::table('banks')->pluck('id')->toArray();
                $bankId = !empty($banks) ? $banks[array_rand($banks)] : null;
            } elseif ($rand <= 70) {
                // 30% نقدی
                $cashBoxes = DB::table('cash_boxes')->pluck('id')->toArray();
                $cashBoxId = !empty($cashBoxes) ? $cashBoxes[array_rand($cashBoxes)] : null;
            } else {
                // 30% چک
                // چک ID رو بعداً از جدول cheques می‌گیریم
                $chequeId = null;
            }
            
            $paymentId = DB::table('supplier_payments')->insertGetId([
                'payment_number' => 'SPY-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($paymentCount, 6, '0', STR_PAD_LEFT),
                'supplier_id' => $invoice->supplier_id,
                'supplier_invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $paymentAmount,
                'currency_code' => $invoice->currency_code,
                'fx_rate_at_payment' => $invoice->fx_rate_at_invoice ?? 1,
                'amount_base_at_payment' => $paymentAmount * ($invoice->fx_rate_at_invoice ?? 1),
                'fx_difference_irr' => 0,
                'payment_date' => $paymentDate,
                'bank_id' => $bankId,
                'cash_box_id' => $cashBoxId,
                'cheque_id' => $chequeId,
                'status' => 'completed',
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // محاسبه مانده جدید
            $newPaidAmount = $invoice->paid_amount + $paymentAmount;
            $newBalanceDue = $invoice->total_amount - $newPaidAmount;
            $newPaymentStatus = $newBalanceDue <= 0 ? 'paid' : 
                ($newPaidAmount > 0 ? 'partially_paid' : 'unpaid');
            
            // update کردن فاکتور
            DB::table('supplier_invoices')
                ->where('id', $invoice->id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'balance_due' => max(0, $newBalanceDue),
                    'payment_status' => $newPaymentStatus,
                    'updated_at' => now(),
                ]);
        }
        
        $this->command->info("    💸 پرداخت‌ها: {$paymentCount} پرداخت - " . number_format($totalPaidAmount / 1000000000, 1) . "B تومان");
        
        return ['payments' => $paymentCount, 'amount' => $totalPaidAmount];
    }
}
