<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class PaymentsSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // چک کردن پرداخت‌های موجود برای این ماه
        $monthPrefix = 'PAY-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existingCount = DB::table('customer_payments')
            ->where('payment_number', 'LIKE', $monthPrefix . '%')
            ->count();
        
        if ($existingCount > 0) {
            $this->command->info("    💳 دریافت‌ها: {$existingCount} پرداخت موجود (skip)");
            return ['payments' => $existingCount];
        }
        
        // گرفتن فاکتورهای unpaid یا partially_paid مشتری
        $unpaidInvoices = DB::table('customer_invoices')
            ->where(function($query) {
                $query->where('payment_status', 'unpaid')
                      ->orWhere('payment_status', 'partially_paid');
            })
            ->where('balance_due', '>', 0)
            ->limit(300) // حداکثر 300 فاکتور در ماه
            ->get();
        
        if ($unpaidInvoices->isEmpty()) {
            $this->command->info("    💳 دریافت‌ها: 0 مورد (فاکتور unpaid وجود ندارد)");
            return ['payments' => 0];
        }
        
        $payments = [];
        $settlements = [];
        $paymentCount = 0;
        $totalPaidAmount = 0;
        
        // دریافت payment methods موجود
        $paymentMethods = DB::table('payment_methods')->pluck('id')->toArray();
        if (empty($paymentMethods)) {
            $this->command->warn("    ⚠️  هیچ روش پرداختی یافت نشد!");
            return ['payments' => 0];
        }
        
        foreach ($unpaidInvoices as $invoice) {
            // 80% احتمال پرداخت کامل، 20% پرداخت جزئی
            $paymentAmount = rand(0, 10) > 2 
                ? $invoice->balance_due 
                : $invoice->balance_due * (rand(30, 70) / 100);
            
            $paymentDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            
            $paymentCount++;
            $totalPaidAmount += $paymentAmount;
            
            // انتخاب روش پرداخت و تعیین bank/cashbox
            $paymentMethodId = $paymentMethods[array_rand($paymentMethods)];
            $bankId = null;
            $cashBoxId = null;
            $posTerminalId = null;
            
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
                // 30% POS
                $posTerminals = DB::table('pos_terminals')->pluck('id')->toArray();
                $posTerminalId = !empty($posTerminals) ? $posTerminals[array_rand($posTerminals)] : null;
            }
            
            $paymentId = DB::table('customer_payments')->insertGetId([
                'payment_number' => 'PAY-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($paymentCount, 6, '0', STR_PAD_LEFT),
                'customer_id' => $invoice->customer_id,
                'store_id' => null, // فعلاً null
                'customer_invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $paymentAmount,
                'currency_code' => $invoice->currency_code,
                'fx_rate' => $invoice->fx_rate,
                'amount_base' => $paymentAmount * $invoice->fx_rate,
                'payment_date' => $paymentDate,
                'bank_id' => $bankId,
                'cash_box_id' => $cashBoxId,
                'pos_terminal_id' => $posTerminalId,
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
            DB::table('customer_invoices')
                ->where('id', $invoice->id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'balance_due' => max(0, $newBalanceDue),
                    'payment_status' => $newPaymentStatus,
                    'updated_at' => now(),
                ]);
            
            // ایجاد settlement (تسویه) برای هر 10 پرداخت
            if ($paymentCount % 10 == 0) {
                $settlements[] = [
                    'settlement_number' => 'SET-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad(count($settlements) + 1, 4, '0', STR_PAD_LEFT),
                    'settlement_type' => 'customer', // تسویه با مشتری
                    'party_type' => 'customer',
                    'party_id' => $invoice->customer_id,
                    'store_id' => null, // فعلاً null
                    'total_invoices' => 1,
                    'total_payments' => 1,
                    'settlement_amount' => $paymentAmount,
                    'settlement_date' => $paymentDate,
                    'currency_code' => $invoice->currency_code,
                    'status' => 'completed',
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Bulk insert settlements
        if (count($settlements) > 0) {
            $this->bulkInsert('settlements', $settlements, 100);
        }
        
        $this->command->info("    💳 دریافت‌ها: {$paymentCount} پرداخت - " . number_format($totalPaidAmount / 1000000000, 1) . "B تومان");
        
        return ['payments' => $paymentCount, 'amount' => $totalPaidAmount];
    }
}
