<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;

class SalesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        $dailyInvoices = rand(130, 150);
        $daysInMonth = 30;
        $totalInvoices = $dailyInvoices * $daysInMonth;
        $totalAmount = $totalInvoices * rand(5000000, 7000000);
        
        $this->command->info("    💰 فروش: {$totalInvoices} فاکتور - " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['invoices' => $totalInvoices, 'amount' => $totalAmount];
    }
}
