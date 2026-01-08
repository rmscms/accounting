<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Events\FiscalYearClosedEvent;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت سال‌های مالی
 */
class FiscalYearService
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ایجاد سال مالی جدید
     */
    public function createFiscalYear(array $data): FiscalYear
    {
        // غیرفعال کردن سال فعلی اگر موجود باشد
        if (!empty($data['is_current']) && $data['is_current']) {
            FiscalYear::where('is_current', true)->update(['is_current' => false]);
        }

        return FiscalYear::create($data);
    }

    /**
     * دریافت سال مالی فعلی
     */
    public function getCurrentFiscalYear(): ?FiscalYear
    {
        return FiscalYear::where('is_current', true)->first();
    }

    /**
     * بستن سال مالی
     */
    public function closeFiscalYear(int $yearId, int $closedByUserId): bool
    {
        $fiscalYear = FiscalYear::findOrFail($yearId);

        if ($fiscalYear->status === 'closed') {
            throw new \Exception('سال مالی قبلاً بسته شده است');
        }

        DB::beginTransaction();
        try {
            // قفل سال مالی
            $fiscalYear->update(['status' => 'locked']);

            // محاسبه مانده‌ها
            $balances = $this->calculateYearEndBalances($fiscalYear);

            // بستن نهایی
            $fiscalYear->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $closedByUserId,
            ]);

            // Dispatch Event
            event(new FiscalYearClosedEvent($fiscalYear, $closedByUserId, $balances));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * محاسبه مانده‌های پایان سال
     */
    protected function calculateYearEndBalances(FiscalYear $fiscalYear): array
    {
        // محاسبه مانده تمام حساب‌ها در پایان سال
        $accounts = \RMS\Accounting\Models\Account::where('active', true)->get();
        $balances = [];

        foreach ($accounts as $account) {
            $balance = $this->ledgerService->getBalance(
                accountId: $account->id,
                toDate: $fiscalYear->end_date
            );

            $balances[$account->id] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'debit' => $balance['debit'],
                'credit' => $balance['credit'],
                'balance' => $balance['balance'],
            ];
        }

        return $balances;
    }

    /**
     * ایجاد سال مالی بعدی
     */
    public function createNextFiscalYear(int $currentYearId): FiscalYear
    {
        $currentYear = FiscalYear::findOrFail($currentYearId);

        $nextYearCode = (int)$currentYear->year_code + 1;

        return $this->createFiscalYear([
            'year_code' => (string)$nextYearCode,
            'start_date' => date('Y-01-01', strtotime('+1 year', strtotime($currentYear->start_date))),
            'end_date' => date('Y-12-31', strtotime('+1 year', strtotime($currentYear->end_date))),
            'status' => 'open',
            'is_current' => true,
        ]);
    }
}
