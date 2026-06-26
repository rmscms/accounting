<?php

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Events\FiscalYearClosedEvent;
use RMS\Accounting\Models\{
    Account,
    AccountingDocument,
    FiscalYear
};

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

            // بستن نهایی (سال بسته نباید همچنان current بماند)
            $fiscalYear->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $closedByUserId,
                'is_current' => false,
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
     * ماندهٔ حساب‌ها در پایان سال (برای رویداد / گزارش).
     */
    public function yearEndBalancesForFiscalYear(FiscalYear $fiscalYear): array
    {
        return $this->calculateYearEndBalances($fiscalYear);
    }

    /**
     * محاسبه مانده‌های پایان سال
     */
    protected function calculateYearEndBalances(FiscalYear $fiscalYear): array
    {
        $accounts = Account::where('active', true)->get();
        $balances = [];
        $start = Carbon::parse((string) $fiscalYear->start_date)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse((string) $fiscalYear->end_date)->endOfDay()->format('Y-m-d H:i:s');

        $aggregates = \RMS\Accounting\Models\FinancialLedger::query()
            ->selectRaw('financial_ledgers.account_id, SUM(financial_ledgers.debit_amount) as debit_sum, SUM(financial_ledgers.credit_amount) as credit_sum')
            ->whereBetween('financial_ledgers.created_at', [$start, $end])
            ->where(static function ($query): void {
                $query->whereNull('financial_ledgers.accounting_document_id')
                    ->orWhereIn('financial_ledgers.accounting_document_id', AccountingDocument::query()
                        ->select('id')
                        ->whereIn('status', [AccountingDocument::STATUS_POSTED, AccountingDocument::STATUS_REVERSED]));
            })
            ->groupBy('financial_ledgers.account_id')
            ->get()
            ->keyBy('account_id');

        foreach ($accounts as $account) {
            $agg = $aggregates->get($account->id);
            $debit = round((float) ($agg->debit_sum ?? 0), 4);
            $credit = round((float) ($agg->credit_sum ?? 0), 4);
            $balance = $account->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);

            $balances[$account->id] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->account_type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round((float) $balance, 4),
            ];
        }

        return $balances;
    }

    /**
     * ساخت خودکار سند افتتاحیه سال جدید از مانده اختتامیه سال بسته.
     * حساب‌های موقت (درآمد/هزینه) به سال جدید منتقل نمی‌شوند.
     */
    public function createOpeningEntryFromYearEndBalances(FiscalYear $closedYear, FiscalYear $nextYear, int $createdByUserId = 0): ?AccountingDocument
    {
        $yearEnd = $this->yearEndBalancesForFiscalYear($closedYear);
        $entries = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($yearEnd as $accountId => $payload) {
            $accountType = (string) ($payload['account_type'] ?? '');
            if (in_array($accountType, [Account::TYPE_INCOME, Account::TYPE_EXPENSE], true)) {
                continue;
            }

            $balance = round((float) ($payload['balance'] ?? 0), 4);
            if (abs($balance) < 0.0001) {
                continue;
            }

            $isDebitNormal = in_array($accountType, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);
            $debit = 0.0;
            $credit = 0.0;

            if ($balance > 0) {
                if ($isDebitNormal) {
                    $debit = $balance;
                } else {
                    $credit = $balance;
                }
            } else {
                $abs = abs($balance);
                if ($isDebitNormal) {
                    $credit = $abs;
                } else {
                    $debit = $abs;
                }
            }

            $entries[] = [
                'account_id' => (int) $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => trans('accounting::accounting.fiscal_year_close.wizard.opening_entry_line_desc', ['year' => $nextYear->year_code]),
            ];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($entries === []) {
            return null;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \RuntimeException(trans('accounting::accounting.fiscal_year_close.wizard.errors.opening_entry_not_balanced'));
        }

        return $this->ledgerService->recordTransaction([
            'document_type' => AccountingDocument::TYPE_OPENING,
            'fiscal_year_id' => $nextYear->id,
            'reference_type' => 'fiscal_year_opening',
            'reference_id' => $closedYear->id,
            'description' => trans('accounting::accounting.fiscal_year_close.wizard.opening_document_description', [
                'closed' => $closedYear->year_code,
                'next' => $nextYear->year_code,
            ]),
            'created_by_user_id' => $createdByUserId > 0 ? $createdByUserId : null,
        ], $entries);
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

    /**
     * سال مالی جاری را برمی‌گرداند؛ در صورت نبود، رکورد سال تقویمی جاری را می‌سازد یا نزدیک‌ترین سال موجود را فعال می‌کند (مثل FiscalYearsSeeder).
     */
    public function getOrCreateCurrentFiscalYear(): FiscalYear
    {
        $current = FiscalYear::where('is_current', true)->first();
        if ($current !== null) {
            return $current;
        }

        $calendarYear = (int) now()->year;
        $yearCode = (string) $calendarYear;

        $forThisYear = FiscalYear::where('year_code', $yearCode)->first();
        if ($forThisYear !== null) {
            $forThisYear->setCurrent();

            return $forThisYear->fresh();
        }

        $latest = FiscalYear::query()->orderByDesc('start_date')->first();
        if ($latest !== null) {
            $latest->setCurrent();

            return $latest->fresh();
        }

        return FiscalYear::create([
            'year_code' => $yearCode,
            'start_date' => sprintf('%d-01-01', $calendarYear),
            'end_date' => sprintf('%d-12-31', $calendarYear),
            'status' => FiscalYear::STATUS_OPEN,
            'is_current' => true,
        ]);
    }
}
