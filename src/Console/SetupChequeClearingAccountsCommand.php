<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RMS\Accounting\Services\ChequeClearingAccountSetupService;

class SetupChequeClearingAccountsCommand extends Command
{
    protected $signature = 'accounting:setup-cheque-clearing-accounts
                            {--no-env : فقط ایجاد حساب در دیتابیس؛ بدون تغییر فایل .env}';

    protected $description = 'ایجاد حساب‌های معین انتظامی چک (در صورت نبود) و درج ACCOUNTING_ACC_CHEQUE_* در .env';

    public function handle(ChequeClearingAccountSetupService $service): int
    {
        $writeEnv = ! $this->option('no-env');

        try {
            $r = $service->run(writeEnv: $writeEnv);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('چک دریافتی (انتظامی): ID ' . $r['receivable_account_id'] . ($r['receivable_created'] ? ' [جدید]' : ' [موجود]'));
        $this->info('چک پرداختی (انتظامی): ID ' . $r['payable_account_id'] . ($r['payable_created'] ? ' [جدید]' : ' [موجود]'));

        if ($writeEnv) {
            if ($r['env_written']) {
                $this->info('فایل .env به‌روز شد.');
                try {
                    Artisan::call('config:clear');
                    $this->info('کش پیکربندی پاک شد (config:clear).');
                } catch (\Throwable $e) {
                    $this->warn('config:clear اجرا نشد: ' . $e->getMessage());
                }
            } else {
                $this->warn('نوشتن .env انجام نشد: ' . ($r['env_error'] ?? 'نامشخص'));
            }
        } else {
            $this->comment('گزینه --no-env: متغیرهای محیط را خودتان تنظیم کنید.');
        }

        return self::SUCCESS;
    }
}
