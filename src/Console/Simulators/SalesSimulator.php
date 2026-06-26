<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class SalesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // چک کردن فاکتورهای موجود برای این ماه
        $monthPrefix = 'INV-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existingCount = DB::table('customer_invoices')
            ->where('invoice_number', 'LIKE', $monthPrefix . '%')
            ->count();
        
        if ($existingCount > 0) {
            $this->command->info("    💰 فروش: {$existingCount} فاکتور موجود (skip)");
            $existingTotal = DB::table('customer_invoices')
                ->where('invoice_number', 'LIKE', $monthPrefix . '%')
                ->sum('total_amount');
            return ['invoices' => $existingCount, 'amount' => $existingTotal];
        }
        
        // دریافت لیست مشتریان
        $customerIds = DB::table('customers')->pluck('id')->toArray();
        if (empty($customerIds)) {
            $this->command->warn("    ⚠️  هیچ مشتری‌ای یافت نشد!");
            return ['invoices' => 0, 'amount' => 0];
        }
        
        $dailyInvoices = rand(10, 15); // تعداد فاکتور در روز
        $daysInMonth = 30;
        $totalInvoices = $dailyInvoices * $daysInMonth;
        
        $invoices = [];
        $totalAmount = 0;
        
        for ($i = 1; $i <= $totalInvoices; $i++) {
            $customerId = $customerIds[array_rand($customerIds)];
            $subtotal = rand(5000000, 50000000); // 5M - 50M
            $taxAmount = $subtotal * 0.09;
            $total = $subtotal + $taxAmount;
            $paidAmount = rand(0, 10) > 2 ? $total : 0;
            $balanceDue = $total - $paidAmount;
            
            $day = rand(1, min(30, $daysInMonth));
            $invoiceDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            
            $invoices[] = [
                'customer_id' => $customerId,
                'store_id' => null, // فعلاً null (چون جدول stores وجود ندارد)
                'invoice_number' => 'INV-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'invoice_date' => $invoiceDate,
                'due_date' => $invoiceDate,
                'currency_code' => 'IRT',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $total,
                'fx_rate' => 1,
                'amount_base' => $total,
                'payment_status' => $paidAmount > 0 ? 'paid' : 'unpaid',
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $totalAmount += $total;
        }
        
        // Bulk insert
        $this->bulkInsert('customer_invoices', $invoices, 500);
        
        $this->command->info("    💰 فروش: {$totalInvoices} فاکتور - " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['invoices' => $totalInvoices, 'amount' => $totalAmount];
    }
}
