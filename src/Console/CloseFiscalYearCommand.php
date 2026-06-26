<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Services\FiscalYearCloseOrchestrationService;

/**
 * دستور بستن سال مالی (مسیر واحد با ادمین)
 */
class CloseFiscalYearCommand extends Command
{
    protected $signature = 'accounting:close-fiscal-year
                            {year_id : شناسه سال مالی}
                            {--force : بستن بدون تایید}
                            {--full-entries : بستن با اسناد اختتامیه (مالیات، سود انباشته)}
                            {--create-next : ایجاد خودکار سال بعد}';

    protected $description = 'بستن سال مالی و همگام‌سازی سال جاری (همان سرویس orchestration)';

    public function handle(FiscalYearCloseOrchestrationService $orchestration): int
    {
        $yearId = (int) $this->argument('year_id');
        $fiscalYear = FiscalYear::findOrFail($yearId);

        if ($fiscalYear->status === FiscalYear::STATUS_CLOSED) {
            $this->error('این سال مالی قبلاً بسته شده است.');

            return 1;
        }

        $this->info("بستن سال مالی: {$fiscalYear->year_code}");
        $this->line('تاریخ شروع: '.$fiscalYear->start_date);
        $this->line('تاریخ پایان: '.$fiscalYear->end_date);

        if (! $this->option('force')) {
            if (! $this->confirm('آیا مطمئن هستید؟ این عملیات غیرقابل بازگشت است.')) {
                $this->info('عملیات لغو شد.');

                return 0;
            }
        }

        $userId = (int) (\RMS\Accounting\Support\AuditActor::actorId() ?? 1);
        $createNext = (bool) $this->option('create-next');

        try {
            if ($this->option('full-entries')) {
                $this->info('اجرای بستن با اسناد اختتامیه...');
                $orchestration->closeWithClosingEntries($yearId, $userId, null, $createNext);
            } else {
                $this->info('اجرای بستن اداری...');
                $orchestration->closeAdministrative($yearId, $userId, null, $createNext);
            }
        } catch (\Throwable $e) {
            $this->error('خطا در بستن سال مالی: '.$e->getMessage());

            return 1;
        }

        $this->info('سال مالی با موفقیت بسته شد.');

        return 0;
    }
}
