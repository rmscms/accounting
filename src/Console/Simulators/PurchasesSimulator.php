<?php

namespace RMS\Accounting\Console\Simulators;

class PurchasesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        $invoices = rand(300, 500);
        $totalAmount = $invoices * rand(30000000, 50000000);
        
        $this->command->info("    🛒 خرید: {$invoices} فاکتور - " . number_format($totalAmount / 1000000000, 1) . "B تومان");
        
        return ['invoices' => $invoices, 'amount' => $totalAmount];
    }
}
