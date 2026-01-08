<?php

namespace RMS\Accounting\Console\Simulators;

class ChequesSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): array
    {
        $cheques = rand(100, 150);
        
        $this->command->info("    📝 چک‌ها: {$cheques} عدد");
        
        return ['cheques' => $cheques];
    }
}
