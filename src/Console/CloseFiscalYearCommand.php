<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Services\DocumentService;
use Illuminate\Support\Facades\DB;

/**
 * دستور بستن سال مالی
 */
class CloseFiscalYearCommand extends Command
{
    protected $signature = 'accounting:close-fiscal-year 
                            {year_id : شناسه سال مالی} 
                            {--force : بستن بدون تایید}';

    protected $description = 'بستن سال مالی و انتقال مانده‌ها به سال بعد';

    protected LedgerService $ledgerService;
    protected DocumentService $documentService;

    public function __construct(LedgerService $ledgerService, DocumentService $documentService)
    {
        parent::__construct();
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
    }

    public function handle()
    {
        $yearId = $this->argument('year_id');
        $fiscalYear = FiscalYear::findOrFail($yearId);

        if ($fiscalYear->status === 'closed') {
            $this->error('❌ این سال مالی قبلاً بسته شده است.');
            return 1;
        }

        $this->info("🔒 بستن سال مالی: {$fiscalYear->year_code}");
        $this->line("تاریخ شروع: {$fiscalYear->start_date}");
        $this->line("تاریخ پایان: {$fiscalYear->end_date}");

        if (!$this->option('force')) {
            if (!$this->confirm('آیا مطمئن هستید؟ این عملیات غیرقابل بازگشت است.')) {
                $this->info('عملیات لغو شد.');
                return 0;
            }
        }

        DB::beginTransaction();
        try {
            // 1. قفل سال مالی
            $this->info('🔐 قفل سال مالی...');
            $fiscalYear->update(['status' => 'locked']);

            // 2. محاسبه مانده حساب‌ها
            $this->info('💰 محاسبه مانده حساب‌ها...');
            $accounts = Account::where('active', true)->get();
            $balances = [];

            foreach ($accounts as $account) {
                $balance = $this->ledgerService->getBalance($account->id);
                $balances[$account->id] = $balance['balance'];
            }

            // 3. بستن حساب‌های موقت (درآمد و هزینه) به سود و زیان
            $this->info('📊 بستن حساب‌های موقت...');
            $this->closeTemporaryAccounts($fiscalYear);

            // 4. بستن نهایی سال مالی
            $fiscalYear->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => auth()->id() ?? 1,
            ]);

            // 5. ایجاد سال مالی جدید (اختیاری)
            $this->info('📅 ایجاد سال مالی جدید؟');
            if ($this->confirm('آیا می‌خواهید سال مالی بعدی را ایجاد کنید؟')) {
                $this->createNextFiscalYear($fiscalYear);
            }

            DB::commit();
            $this->info('✅ سال مالی با موفقیت بسته شد!');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ خطا در بستن سال مالی: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * بستن حساب‌های موقت (درآمد و هزینه)
     */
    protected function closeTemporaryAccounts(FiscalYear $fiscalYear): void
    {
        // محاسبه مجموع درآمدها
        $revenueAccounts = Account::where('account_type', 'revenue')->pluck('id');
        $totalRevenue = 0;

        foreach ($revenueAccounts as $accountId) {
            $balance = $this->ledgerService->getBalance($accountId);
            $totalRevenue += $balance['credit'] - $balance['debit'];
        }

        // محاسبه مجموع هزینه‌ها
        $expenseAccounts = Account::where('account_type', 'expense')->pluck('id');
        $totalExpense = 0;

        foreach ($expenseAccounts as $accountId) {
            $balance = $this->ledgerService->getBalance($accountId);
            $totalExpense += $balance['debit'] - $balance['credit'];
        }

        // سود/زیان
        $profitLoss = $totalRevenue - $totalExpense;

        $this->line("درآمد: " . number_format($totalRevenue));
        $this->line("هزینه: " . number_format($totalExpense));
        $this->line("سود/زیان: " . number_format($profitLoss));

        // ثبت سند انتقال به سود و زیان انباشته
        // (پیاده‌سازی کامل در نسخه بعدی)
    }

    /**
     * ایجاد سال مالی بعدی
     */
    protected function createNextFiscalYear(FiscalYear $currentYear): void
    {
        $nextYearCode = (int)$currentYear->year_code + 1;

        FiscalYear::create([
            'year_code' => (string)$nextYearCode,
            'start_date' => date('Y-01-01', strtotime("+1 year", strtotime($currentYear->start_date))),
            'end_date' => date('Y-12-31', strtotime("+1 year", strtotime($currentYear->end_date))),
            'status' => 'open',
            'is_current' => true,
        ]);

        // غیرفعال کردن سال قبل
        $currentYear->update(['is_current' => false]);

        $this->info("✅ سال مالی {$nextYearCode} ایجاد شد.");
    }
}
