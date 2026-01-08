<?php

namespace RMS\Accounting\Console\Simulators;

class ExpensesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        $expenses = 12;
        $totalAmount = 1200000000;
        
        $this->command->info("    💸 هزینه‌ها: " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['expenses' => $expenses, 'amount' => $totalAmount];
    }
}
