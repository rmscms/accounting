<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class ChequesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // دریافت payments این ماه که با چک انجام نشدن
        $monthStart = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $monthEnd = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-30';
        
        $payments = DB::table('customer_payments')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->whereNull('cheque_id')
            ->limit(100) // حداکثر 100 چک در ماه
            ->get();
        
        if ($payments->isEmpty()) {
            $this->command->info("    📝 چک‌ها: 0 عدد (پرداخت وجود ندارد)");
            return ['cheques' => 0];
        }
        
        // دریافت بانک‌های موجود
        $bankIds = DB::table('banks')->pluck('id')->toArray();
        if (empty($bankIds)) {
            $this->command->warn("    ⚠️  هیچ بانکی یافت نشد!");
            return ['cheques' => 0];
        }
        
        $cheques = [];
        $chequeCount = 0;
        
        // فقط 30% از payments با چک هستن
        foreach ($payments as $payment) {
            if (rand(0, 10) > 7) { // 30% احتمال
                continue;
            }
            
            $chequeCount++;
            $issueDate = $payment->payment_date;
            $dueDate = date('Y-m-d', strtotime($issueDate . ' +30 days'));
            
            // دریافت اطلاعات مشتری
            $customer = DB::table('customers')->find($payment->customer_id);
            
            $chequeId = DB::table('cheques')->insertGetId([
                'cheque_number' => str_pad(rand(1000000, 9999999), 8, '0', STR_PAD_LEFT),
                'bank_id' => $bankIds[array_rand($bankIds)],
                'cheque_type' => 'received', // چک دریافتی از مشتری
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'payer_name' => $customer ? $customer->name : 'مشتری',
                'payer_account' => rand(1000000000, 9999999999),
                'payee_name' => 'شرکت',
                'status' => rand(0, 10) > 1 ? 'cashed' : 'pending', // 90% وصول شده
                'cashed_at' => rand(0, 10) > 1 ? $dueDate : null,
                'payment_id' => $payment->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // update payment با cheque_id
            DB::table('customer_payments')
                ->where('id', $payment->id)
                ->update([
                    'cheque_id' => $chequeId,
                    'updated_at' => now(),
                ]);
        }
        
        // چک‌های پرداختی به تامین‌کنندگان (تعداد کمتر)
        $supplierPaymentCount = rand(5, 15);
        $suppliers = DB::table('suppliers')->limit($supplierPaymentCount)->get();
        
        foreach ($suppliers as $supplier) {
            $chequeCount++;
            $issueDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $dueDate = date('Y-m-d', strtotime($issueDate . ' +60 days'));
            
            $cheques[] = [
                'cheque_number' => str_pad(rand(1000000, 9999999), 8, '0', STR_PAD_LEFT),
                'bank_id' => $bankIds[array_rand($bankIds)],
                'cheque_type' => 'issued', // چک پرداختی به تامین‌کننده
                'amount' => rand(50000000, 200000000),
                'currency_code' => 'IRT',
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'payer_name' => 'شرکت',
                'payee_name' => $supplier->name,
                'payee_account' => rand(1000000000, 9999999999),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Bulk insert چک‌های پرداختی
        if (count($cheques) > 0) {
            $this->bulkInsert('cheques', $cheques, 100);
        }
        
        $this->command->info("    📝 چک‌ها: {$chequeCount} عدد");
        
        return ['cheques' => $chequeCount];
    }
}
