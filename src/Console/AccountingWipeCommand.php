<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Services\AccountingDataWipeService;
use RMS\Accounting\Services\AccountingWipe\WipeMode;
use RMS\Accounting\Services\AccountingWipe\WipeOptions;

/**
 * پاک‌سازی اسناد و دفتر کل (خطرناک — پیش‌فرض فقط گزارش dry-run).
 */
class AccountingWipeCommand extends Command
{
    protected $signature = 'accounting:wipe
                            {--mode=documents : documents یا accounting-reset یا all-tables}
                            {--dry-run : فقط شمارش بدون تغییر (پیش‌فرض بدون --execute)}
                            {--execute : اجرای واقعی حذف/به‌روزرسانی}
                            {--force : برای accounting-reset/all-tables معادل تأیید (همراه با --execute)}
                            {--confirm= : برای accounting-reset/all-tables مقدار RESET}';

    protected $description = 'پاک‌سازی اسناد حسابداری، دفتر مالی، سال مالی و (اختیاری) دادهٔ عملیاتی';

    public function handle(AccountingDataWipeService $wipeService): int
    {
        $modeStr = (string) $this->option('mode');
        $mode = WipeMode::tryFromString($modeStr);
        if ($mode === null) {
            $this->error('مقدار نامعتبر برای --mode. مقادیر مجاز: documents, accounting-reset, all-tables');

            return 1;
        }

        if ($this->option('execute') && $this->option('dry-run')) {
            $this->error('نمی‌توان هم‌زمان ‎--execute‎ و ‎--dry-run‎ استفاده کرد.');

            return 1;
        }

        $execute = (bool) $this->option('execute');
        $dryRun = ! $execute;

        $confirm = strtoupper(trim((string) $this->option('confirm')));
        $force = (bool) $this->option('force');
        $confirmedReset = $confirm === 'RESET' || $force;

        if (in_array($mode, [WipeMode::AccountingReset, WipeMode::AllTables], true) && $execute && ! $confirmedReset) {
            $this->error('حالت '.$mode->value.' برای اجرای واقعی نیاز به --confirm=RESET یا --force دارد.');

            return 1;
        }

        $options = match ($mode) {
            WipeMode::Documents => WipeOptions::documents($dryRun),
            WipeMode::AccountingReset => WipeOptions::accountingReset($dryRun, $confirmedReset),
            WipeMode::AllTables => WipeOptions::allTables($dryRun, $confirmedReset),
        };

        $this->warn('این عمل ردپای حسابداری را حذف می‌کند؛ در حالت documents فاکتور/پرداخت ممکن است بماند ولی دیگر سند معتبر ندارد.');
        $this->newLine();

        try {
            $result = $wipeService->run($options);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($result->dryRun) {
            $this->info('حالت dry-run (بدون تغییر در پایگاه). برای اجرا: ‎--execute‎ را اضافه کنید.');
        } else {
            $this->info('پاک‌سازی انجام شد.');
        }

        $this->newLine();
        $rows = [];
        foreach ($result->counts as $key => $value) {
            $rows[] = [$key, $value];
        }
        if ($rows === []) {
            $this->line('هیچ جدول شناخته‌شده‌ای برای شمارش وجود نداشت.');
        } else {
            $this->table(['عمل', 'تعداد'], $rows);
        }

        return 0;
    }
}
