<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class ExpensesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        // چک کردن هزینه‌های موجود برای این ماه
        $monthPrefix = 'EXP-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existingCount = DB::table('expenses')
            ->where('expense_number', 'LIKE', $monthPrefix . '%')
            ->count();
        
        if ($existingCount > 0) {
            $this->command->info("    💸 هزینه‌ها: {$existingCount} مورد موجود (skip)");
            $existingTotal = DB::table('expenses')
                ->where('expense_number', 'LIKE', $monthPrefix . '%')
                ->sum('amount');
            return ['expenses' => $existingCount, 'amount' => $existingTotal];
        }
        
        // دریافت لیست دسته‌بندی هزینه‌ها
        $categoryIds = DB::table('expense_categories')->pluck('id')->toArray();
        if (empty($categoryIds)) {
            $this->command->warn("    ⚠️  هیچ دسته‌بندی هزینه‌ای یافت نشد!");
            return ['expenses' => 0, 'amount' => 0];
        }
        
        $expenseCount = rand(10, 15); // 10-15 هزینه در ماه
        $expenses = [];
        $totalAmount = 0;
        
        $expenseTypes = ['operational', 'salary', 'rent', 'utilities', 'marketing', 'transportation', 'supplies', 'maintenance', 'other'];
        $payeeTypes = ['employee', 'supplier', 'service_provider', 'government', 'other'];
        $payeeNames = [
            'employee' => ['احمد رضایی', 'فاطمه محمدی', 'علی کریمی'],
            'supplier' => ['شرکت پخش مرکزی', 'تامین کنندگان ایران', 'شرکت تجاری آسیا'],
            'service_provider' => ['شرکت خدمات فنی', 'نگهداری ساختمان', 'شرکت حمل و نقل'],
            'government' => ['اداره مالیات', 'شهرداری', 'اداره برق'],
            'other' => ['سایر پرداخت‌ها'],
        ];
        
        for ($i = 1; $i <= $expenseCount; $i++) {
            $amount = rand(50000000, 200000000); // 50M - 200M
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $expenseType = $expenseTypes[array_rand($expenseTypes)];
            $payeeType = $payeeTypes[array_rand($payeeTypes)];
            $payeeName = $payeeNames[$payeeType][array_rand($payeeNames[$payeeType])];
            
            $day = rand(1, 30);
            $expenseDate = $this->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            
            $expenses[] = [
                'expense_number' => 'EXP-' . $this->year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'expense_category_id' => $categoryId,
                'expense_type' => $expenseType,
                'amount' => $amount,
                'currency_code' => 'IRT',
                'fx_rate' => 1,
                'amount_base' => $amount,
                'expense_date' => $expenseDate,
                'payment_status' => 'paid',
                'paid_amount' => $amount,
                'payee_type' => $payeeType,
                'payee_name' => $payeeName,
                'description' => 'هزینه ' . $expenseType . ' ماه ' . $month,
                'status' => 'approved',
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $totalAmount += $amount;
        }
        
        // Bulk insert
        $this->bulkInsert('expenses', $expenses, 500);
        
        $this->command->info("    💸 هزینه‌ها: " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['expenses' => $expenseCount, 'amount' => $totalAmount];
    }
}
