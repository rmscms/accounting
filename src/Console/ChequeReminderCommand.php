<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Models\Cheque;
use Illuminate\Support\Facades\Notification;

/**
 * دستور یادآوری چک‌های سررسید
 */
class ChequeReminderCommand extends Command
{
    protected $signature = 'accounting:cheque-reminder 
                            {--days=7 : تعداد روز قبل از سررسید}';

    protected $description = 'ارسال یادآوری برای چک‌های نزدیک به سررسید';

    public function handle()
    {
        $days = $this->option('days');
        $targetDate = now()->addDays($days)->toDateString();

        $this->info("🔔 بررسی چک‌های سررسید تا {$days} روز آینده...");

        // چک‌های دریافتنی
        $receivableCheques = Cheque::where('cheque_type', 'receivable')
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        // چک‌های پرداختنی
        $payableCheques = Cheque::where('cheque_type', 'payable')
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $targetDate)
            ->get();

        $this->line('');
        $this->line("چک‌های دریافتنی: {$receivableCheques->count()}");
        $this->line("چک‌های پرداختنی: {$payableCheques->count()}");

        // نمایش چک‌ها
        if ($receivableCheques->count() > 0) {
            $this->info('');
            $this->info('📥 چک‌های دریافتنی:');
            $this->table(
                ['شماره', 'بانک', 'مبلغ', 'سررسید'],
                $receivableCheques->map(fn($c) => [
                    $c->cheque_number,
                    $c->bank->name ?? '-',
                    number_format($c->amount),
                    $c->due_date,
                ])
            );
        }

        if ($payableCheques->count() > 0) {
            $this->info('');
            $this->info('📤 چک‌های پرداختنی:');
            $this->table(
                ['شماره', 'بانک', 'مبلغ', 'سررسید'],
                $payableCheques->map(fn($c) => [
                    $c->cheque_number,
                    $c->bank->name ?? '-',
                    number_format($c->amount),
                    $c->due_date,
                ])
            );
        }

        // ارسال نوتیفیکیشن (پیاده‌سازی در نسخه بعدی)
        // Notification::send($users, new ChequeReminderNotification($cheques));

        $this->info('');
        $this->info('✅ بررسی چک‌ها انجام شد.');

        return 0;
    }
}
