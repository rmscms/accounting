<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Services\ReconciliationService;

/**
 * دستور تطبیق خودکار پرداخت‌ها
 */
class AutoReconcileCommand extends Command
{
    protected $signature = 'accounting:auto-reconcile 
                            {--bank= : شناسه بانک} 
                            {--date= : تاریخ مشخص}';

    protected $description = 'تطبیق خودکار پرداخت‌ها با صورت‌حساب بانکی';

    protected ReconciliationService $reconciliationService;

    public function __construct(ReconciliationService $reconciliationService)
    {
        parent::__construct();
        $this->reconciliationService = $reconciliationService;
    }

    public function handle()
    {
        $bankId = $this->option('bank');
        $date = $this->option('date');

        $this->info('🔄 شروع تطبیق خودکار پرداخت‌ها...');

        if ($bankId) {
            $this->line("بانک: {$bankId}");
        }

        if ($date) {
            $this->line("تاریخ: {$date}");
        }

        $results = $this->reconciliationService->autoReconcilePayments($bankId, $date);

        $this->info('');
        $this->info('📊 نتایج:');
        $this->line("✅ تطبیق موفق: {$results['matched']}");
        $this->line("⚠️ دارای اختلاف: {$results['discrepancy']}");
        $this->line("❌ ناموفق: {$results['failed']}");

        $this->info('');
        $this->info('✅ تطبیق خودکار انجام شد.');

        return 0;
    }
}
