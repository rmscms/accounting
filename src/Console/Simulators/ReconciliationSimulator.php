<?php

namespace RMS\Accounting\Console\Simulators;

class ReconciliationSimulator extends BaseSimulator
{
    public function simulate(): void {}
    
    public function simulateMonth(int $month): void
    {
        $this->command->info("    ✅ تطبیق: 3 مورد");
    }
}
