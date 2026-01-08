<?php

namespace RMS\Accounting\Console\Simulators;

class PaymentsSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        $payments = rand(3500, 4000);
        
        $this->command->info("    💳 دریافت‌ها: {$payments} مورد");
        
        return ['payments' => $payments];
    }
}
