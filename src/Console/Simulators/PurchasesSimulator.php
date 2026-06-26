<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class PurchasesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // چک کردن فاکتورهای موجود برای این ماه
        $monthPrefix = 'PI-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existingCount = DB::table('supplier_invoices')
            ->where('invoice_number', 'LIKE', $monthPrefix . '%')
            ->count();
        
        if ($existingCount > 0) {
            $this->command->info("    🛒 خرید: {$existingCount} فاکتور موجود (skip)");
            $existingTotal = DB::table('supplier_invoices')
                ->where('invoice_number', 'LIKE', $monthPrefix . '%')
                ->sum('total_amount');
            return ['invoices' => $existingCount, 'amount' => $existingTotal];
        }
        
        // دریافت لیست تامین‌کنندگان
        $supplierIds = DB::table('suppliers')->pluck('id')->toArray();
        if (empty($supplierIds)) {
            $this->command->warn("    ⚠️  هیچ تامین‌کننده‌ای یافت نشد!");
            return ['invoices' => 0, 'amount' => 0];
        }
        
        $dailyInvoices = rand(5, 10); // کمتر از فروش
        $daysInMonth = 30;
        $totalInvoices = $dailyInvoices * $daysInMonth;
        
        $invoices = [];
        $totalAmount = 0;
        
        for ($i = 1; $i <= $totalInvoices; $i++) {
            $supplierId = $supplierIds[array_rand($supplierIds)];
            $subtotal = rand(10000000, 100000000); // 10M - 100M (خرید گران‌تر از فروش)
            $taxAmount = $subtotal * 0.09;
            $discountAmount = rand(0, 1) > 0.7 ? $subtotal * 0.05 : 0; // 30% احتمال تخفیف
            $total = $subtotal + $taxAmount - $discountAmount;
            $paidAmount = rand(0, 10) > 3 ? $total : 0; // 70% پرداخت شده
            $balanceDue = $total - $paidAmount;
            
            $day = rand(1, min(30, $daysInMonth));
            $invoiceDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dueDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad(min(30, $day + 30), 2, '0', STR_PAD_LEFT);
            
            $invoices[] = [
                'supplier_id' => $supplierId,
                'store_id' => null, // فعلاً null
                'invoice_number' => 'PI-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'supplier_invoice_number' => 'SUP-' . rand(1000, 9999) . '-' . $i,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'currency_code' => 'IRT',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $total,
                'fx_rate_at_invoice' => 1,
                'amount_base_at_invoice' => $total,
                'payment_status' => $paidAmount > 0 ? ($paidAmount >= $total ? 'paid' : 'partially_paid') : 'unpaid',
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $totalAmount += $total;
        }
        
        // Bulk insert
        $this->bulkInsert('supplier_invoices', $invoices, 500);
        
        $this->command->info("    🛒 خرید: {$totalInvoices} فاکتور - " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['invoices' => $totalInvoices, 'amount' => $totalAmount];
    }
}
