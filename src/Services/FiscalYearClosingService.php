<?php

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Models\{
    FiscalYear,
    Account,
    AccountingDocument,
    FinancialLedger
};
use RMS\Core\Models\Setting;

/**
 * سرویس بستن سال مالی
 * محاسبه و ثبت مالیات بر درآمد در پایان سال
 */
class FiscalYearClosingService
{
    protected LedgerService $ledgerService;
    protected ReportService $reportService;
    
    public function __construct(LedgerService $ledgerService, ReportService $reportService)
    {
        $this->ledgerService = $ledgerService;
        $this->reportService = $reportService;
    }
    
    /**
     * بستن سال مالی و محاسبه مالیات بر درآمد
     *
     * @return array<string, mixed>
     */
    public function closeFiscalYear(FiscalYear $fiscalYear, int $closedByUserId): array
    {
        if ($fiscalYear->status === FiscalYear::STATUS_CLOSED) {
            throw new \Exception(trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        if (Schema::hasColumn('fiscal_years', 'is_closed') && $fiscalYear->getAttribute('is_closed')) {
            throw new \Exception(trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        return DB::transaction(function () use ($fiscalYear, $closedByUserId): array {
            // 1. محاسبه صورت سود و زیان
            $incomeStatement = $this->reportService->getIncomeStatement([
                'start_date' => $fiscalYear->start_date?->format('Y-m-d') ?? (string) $fiscalYear->start_date,
                'end_date' => $fiscalYear->end_date?->format('Y-m-d') ?? (string) $fiscalYear->end_date,
            ]);

            $incomeBeforeTax = (float) ($incomeStatement['income_before_tax'] ?? 0);
            $incomeTaxExpense = (float) ($incomeStatement['income_tax_expense'] ?? 0);

            // 2. ثبت مالیات بر درآمد (اگر مثبت باشد)
            $incomeTaxDocument = null;
            if ($incomeTaxExpense > 0) {
                $incomeTaxDocument = $this->recordIncomeTaxExpense(
                    $fiscalYear,
                    $incomeBeforeTax,
                    $incomeTaxExpense
                );
            }

            // 3. بستن حساب‌های موقت (درآمد و هزینه) و انتقال خالص آن‌ها به حساب خلاصه
            $temporaryClose = $this->closeTemporaryAccounts($fiscalYear);

            // 4. انتقال مانده خلاصه درآمد/هزینه به سود انباشته (مسیر سرمایه)
            $retainedEarningsDocument = $this->transferToRetainedEarnings(
                $fiscalYear,
                (int) $temporaryClose['income_summary_account_id'],
                (float) $temporaryClose['income_summary_net']
            );

            // 5. علامت‌گذاری سال مالی به عنوان بسته شده (وضعیت + اسناد اختتامیه)
            $payload = [
                'status' => FiscalYear::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $closedByUserId,
                'is_current' => false,
            ];
            if (Schema::hasColumn('fiscal_years', 'is_closed')) {
                $payload['is_closed'] = true;
            }
            if (Schema::hasColumn('fiscal_years', 'closing_document_id')) {
                $payload['closing_document_id'] = $retainedEarningsDocument?->id ?? $temporaryClose['document_id'];
            }
            $fiscalYear->update($payload);

            return [
                'fiscal_year_id' => $fiscalYear->id,
                'income_before_tax' => $incomeBeforeTax,
                'income_tax_expense' => $incomeTaxExpense,
                'net_income' => (float) $temporaryClose['income_summary_net'],
                'income_tax_document_id' => $incomeTaxDocument?->id,
                'temporary_close_document_id' => $temporaryClose['document_id'],
                'retained_earnings_document_id' => $retainedEarningsDocument?->id,
                'temporary_accounts_closed_count' => $temporaryClose['closed_count'],
                'temporary_non_zero_after_close' => $this->temporaryAccountsSnapshot($fiscalYear)['non_zero_count'],
                'closed_at' => $fiscalYear->closed_at,
            ];
        });
    }

    /**
     * ثبت هزینه مالیات بر درآمد در دفتر کل
     *
     * @param FiscalYear $fiscalYear
     * @param float $incomeBeforeTax
     * @param float $incomeTaxExpense
     * @return AccountingDocument
     */
    protected function recordIncomeTaxExpense(
        FiscalYear $fiscalYear,
        float $incomeBeforeTax,
        float $incomeTaxExpense
    ): AccountingDocument {
        // دریافت حساب‌ها از تنظیمات
        $incomeTaxExpenseAccountId = Setting::get('accounting.income_tax.expense_account_id');
        $incomeTaxPayableAccountId = Setting::get('accounting.income_tax.payable_account_id');

        if (! $incomeTaxExpenseAccountId || ! $incomeTaxPayableAccountId) {
            throw new \Exception('حساب‌های مالیات بر درآمد در تنظیمات مشخص نشده است');
        }

        // ثبت سند مالیات بر درآمد
        return $this->ledgerService->recordTransaction([
            'document_type' => AccountingDocument::TYPE_CLOSING,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_type' => 'fiscal_year_closing',
            'reference_id' => $fiscalYear->id,
            'description' => trans('accounting::accounting.fiscal_year_close.income_tax_document_description', ['code' => $fiscalYear->year_code]),
        ], [
            [
                'account_id' => $incomeTaxExpenseAccountId,
                'debit' => $incomeTaxExpense, // بدهکار: هزینه مالیات
                'credit' => 0,
                'description' => 'هزینه مالیات بر درآمد',
            ],
            [
                'account_id' => $incomeTaxPayableAccountId,
                'debit' => 0,
                'credit' => $incomeTaxExpense, // بستانکار: مالیات پرداختنی
                'description' => 'مالیات بر درآمد پرداختنی',
            ],
        ]);
    }

    /**
     * انتقال سود/زیان خالص به حساب سود و زیان انباشته
     *
     * @param FiscalYear $fiscalYear
     * @param int $incomeSummaryAccountId
     * @param float $incomeSummaryNet
     * @return AccountingDocument|null
     */
    protected function transferToRetainedEarnings(
        FiscalYear $fiscalYear,
        int $incomeSummaryAccountId,
        float $incomeSummaryNet
    ): ?AccountingDocument {
        if (abs($incomeSummaryNet) < 0.0001) {
            return null;
        }

        $retainedEarningsAccountId = $this->resolveRetainedEarningsAccountId();

        // اگر حساب خلاصه و سود انباشته یکی باشد، انتقال جداگانه لازم نیست
        if ($incomeSummaryAccountId === $retainedEarningsAccountId) {
            return null;
        }

        if ($incomeSummaryNet > 0) {
            // سود: بدهکار خلاصه / بستانکار سود انباشته
            return $this->ledgerService->recordTransaction([
                'document_type' => AccountingDocument::TYPE_CLOSING,
                'fiscal_year_id' => $fiscalYear->id,
                'reference_type' => 'fiscal_year_closing',
                'reference_id' => $fiscalYear->id,
                'description' => trans('accounting::accounting.fiscal_year_close.retained_earnings_profit_description', ['code' => $fiscalYear->year_code]),
            ], [
                [
                    'account_id' => $incomeSummaryAccountId,
                    'debit' => $incomeSummaryNet,
                    'credit' => 0,
                ],
                [
                    'account_id' => $retainedEarningsAccountId,
                    'debit' => 0,
                    'credit' => $incomeSummaryNet,
                ],
            ]);
        }

        // زیان: بدهکار سود انباشته / بستانکار خلاصه
        $loss = abs($incomeSummaryNet);

        return $this->ledgerService->recordTransaction([
            'document_type' => AccountingDocument::TYPE_CLOSING,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_type' => 'fiscal_year_closing',
            'reference_id' => $fiscalYear->id,
            'description' => trans('accounting::accounting.fiscal_year_close.retained_earnings_loss_description', ['code' => $fiscalYear->year_code]),
        ], [
            [
                'account_id' => $retainedEarningsAccountId,
                'debit' => $loss,
                'credit' => 0,
            ],
            [
                'account_id' => $incomeSummaryAccountId,
                'debit' => 0,
                'credit' => $loss,
            ],
        ]);
    }

    /**
     * بستن حساب‌های موقت (درآمد و هزینه)
     *
     * @param FiscalYear $fiscalYear
     * @return array{document_id: int|null, closed_count: int, income_summary_account_id: int, income_summary_net: float}
     */
    protected function closeTemporaryAccounts(FiscalYear $fiscalYear): array
    {
        $snapshot = $this->temporaryAccountsSnapshot($fiscalYear);
        $incomeSummaryAccountId = $this->resolveIncomeSummaryAccountId();

        if ($snapshot['non_zero_count'] === 0) {
            return [
                'document_id' => null,
                'closed_count' => 0,
                'income_summary_account_id' => $incomeSummaryAccountId,
                'income_summary_net' => 0.0,
            ];
        }

        $entries = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $closedCount = 0;

        foreach ($snapshot['rows'] as $row) {
            $net = (float) ($row['net'] ?? 0);
            if (abs($net) < 0.0001) {
                continue;
            }

            $closedCount++;
            $amount = abs($net);

            if ($net > 0) {
                // مانده بدهکار دارد => برای صفر شدن باید بستانکار شود
                $entries[] = [
                    'account_id' => (int) $row['account_id'],
                    'debit' => 0.0,
                    'credit' => $amount,
                    'description' => trans('accounting::accounting.fiscal_year_close.wizard.close_temp_entry_desc', ['code' => (string) ($row['code'] ?? '')]),
                ];
                $totalCredit += $amount;
            } else {
                // مانده بستانکار دارد => برای صفر شدن باید بدهکار شود
                $entries[] = [
                    'account_id' => (int) $row['account_id'],
                    'debit' => $amount,
                    'credit' => 0.0,
                    'description' => trans('accounting::accounting.fiscal_year_close.wizard.close_temp_entry_desc', ['code' => (string) ($row['code'] ?? '')]),
                ];
                $totalDebit += $amount;
            }
        }

        $summaryNet = round($totalDebit - $totalCredit, 4);
        if (abs($summaryNet) > 0.0001) {
            $entries[] = [
                'account_id' => $incomeSummaryAccountId,
                'debit' => $summaryNet < 0 ? abs($summaryNet) : 0.0,
                'credit' => $summaryNet > 0 ? $summaryNet : 0.0,
                'description' => trans('accounting::accounting.fiscal_year_close.wizard.close_temp_summary_desc', ['code' => $fiscalYear->year_code]),
            ];
        }

        $document = $this->ledgerService->recordTransaction([
            'document_type' => AccountingDocument::TYPE_CLOSING,
            'fiscal_year_id' => $fiscalYear->id,
            'reference_type' => 'fiscal_year_closing',
            'reference_id' => $fiscalYear->id,
            'description' => trans('accounting::accounting.fiscal_year_close.wizard.temp_close_document_description', ['code' => $fiscalYear->year_code]),
        ], $entries);

        return [
            'document_id' => $document->id,
            'closed_count' => $closedCount,
            'income_summary_account_id' => $incomeSummaryAccountId,
            'income_summary_net' => $summaryNet,
        ];
    }

    /**
     * محاسبه مالیات بر درآمد (بدون ثبت)
     *
     * @param FiscalYear $fiscalYear
     * @return array<string, mixed>
     */
    public function calculateIncomeTax(FiscalYear $fiscalYear): array
    {
        $incomeStatement = $this->reportService->getIncomeStatement([
            'start_date' => $fiscalYear->start_date?->format('Y-m-d') ?? (string) $fiscalYear->start_date,
            'end_date' => $fiscalYear->end_date?->format('Y-m-d') ?? (string) $fiscalYear->end_date,
        ]);

        return [
            'fiscal_year' => $fiscalYear->year_code,
            'period' => [
                'start' => $fiscalYear->start_date,
                'end' => $fiscalYear->end_date,
            ],
            'total_revenue' => (float) (($incomeStatement['revenue']['total'] ?? 0)),
            'total_expenses' => (float) (($incomeStatement['operating_expenses']['total'] ?? 0)) + (float) (($incomeStatement['cost_of_goods_sold'] ?? 0)),
            'income_before_tax' => (float) ($incomeStatement['income_before_tax'] ?? 0),
            'income_tax_rate' => (float) ($incomeStatement['income_tax_rate'] ?? 0),
            'income_tax_expense' => (float) ($incomeStatement['income_tax_expense'] ?? 0),
            'net_income' => (float) ($incomeStatement['net_income'] ?? 0),
        ];
    }

    /**
     * مانده حساب‌های موقت در سال مالی (برای precheck/postcheck ویزارد).
     *
     * @return array{rows: array<int, array<string, mixed>>, non_zero_count: int, totals: array<string, float>, income_summary_net_estimate: float}
     */
    public function temporaryAccountsSnapshot(FiscalYear $fiscalYear): array
    {
        $accounts = Account::query()
            ->where('active', true)
            ->whereIn('account_type', [Account::TYPE_INCOME, Account::TYPE_EXPENSE])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);

        $ids = $accounts->pluck('id')->all();
        if ($ids === []) {
            return [
                'rows' => [],
                'non_zero_count' => 0,
                'totals' => [
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'net' => 0.0,
                ],
                'income_summary_net_estimate' => 0.0,
            ];
        }

        $start = Carbon::parse((string) $fiscalYear->start_date)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse((string) $fiscalYear->end_date)->endOfDay()->format('Y-m-d H:i:s');

        $ledgerByAccount = FinancialLedger::query()
            ->selectRaw('financial_ledgers.account_id, SUM(financial_ledgers.debit_amount) as debit_sum, SUM(financial_ledgers.credit_amount) as credit_sum')
            ->whereBetween('financial_ledgers.created_at', [$start, $end])
            ->where(static function ($query): void {
                $query->whereNull('financial_ledgers.accounting_document_id')
                    ->orWhereIn('financial_ledgers.accounting_document_id', AccountingDocument::query()
                        ->select('id')
                        ->whereIn('status', [AccountingDocument::STATUS_POSTED, AccountingDocument::STATUS_REVERSED]));
            })
            ->whereIn('financial_ledgers.account_id', $ids)
            ->groupBy('financial_ledgers.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = [];
        $nonZero = 0;
        $sumDebit = 0.0;
        $sumCredit = 0.0;
        $sumNet = 0.0;

        foreach ($accounts as $account) {
            $agg = $ledgerByAccount->get($account->id);
            $debit = round((float) ($agg->debit_sum ?? 0), 4);
            $credit = round((float) ($agg->credit_sum ?? 0), 4);
            $net = round($debit - $credit, 4); // مثبت=بدهکار, منفی=بستانکار
            if (abs($net) < 0.0001) {
                $net = 0.0;
            }
            if ($net !== 0.0) {
                $nonZero++;
            }

            $sumDebit += $debit;
            $sumCredit += $credit;
            $sumNet += $net;

            $rows[] = [
                'account_id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => (string) $account->account_type,
                'debit' => $debit,
                'credit' => $credit,
                'net' => $net,
            ];
        }

        return [
            'rows' => $rows,
            'non_zero_count' => $nonZero,
            'totals' => [
                'debit' => round($sumDebit, 4),
                'credit' => round($sumCredit, 4),
                'net' => round($sumNet, 4),
            ],
            // مانده‌ای که باید به سود انباشته منتقل شود (بعد از بستن temp ها و قبل از مالیات)
            'income_summary_net_estimate' => round($sumCredit - $sumDebit, 4),
        ];
    }

    protected function resolveRetainedEarningsAccountId(): int
    {
        $fromSettings = (int) (Setting::get('accounting.retained_earnings_account_id') ?? 0);
        if ($fromSettings > 0 && Account::query()->whereKey($fromSettings)->exists()) {
            return $fromSettings;
        }

        $byCode = Account::query()->where('code', '3200')->first(['id']);
        if ($byCode !== null) {
            return (int) $byCode->id;
        }

        $fallback = Account::query()
            ->where('account_type', Account::TYPE_EQUITY)
            ->where(static function ($query): void {
                $query->where('name', 'LIKE', '%انباشته%')
                    ->orWhere('name', 'LIKE', '%سود انباشته%')
                    ->orWhere('name', 'LIKE', '%سود (زیان) انباشته%')
                    ->orWhere('name', 'LIKE', '%retained%');
            })
            ->orderBy('code')
            ->first(['id']);

        if ($fallback !== null) {
            return (int) $fallback->id;
        }

        throw new \RuntimeException('حساب سود انباشته در تنظیمات مشخص نشده و fallback هم یافت نشد.');
    }

    protected function resolveIncomeSummaryAccountId(): int
    {
        $fromSettings = (int) (Setting::get('accounting.income_summary_account_id') ?? 0);
        if ($fromSettings > 0 && Account::query()->whereKey($fromSettings)->exists()) {
            return $fromSettings;
        }

        $byCode = Account::query()->where('code', '3900')->first(['id']);
        if ($byCode !== null) {
            return (int) $byCode->id;
        }

        $fallback = Account::query()
            ->where('account_type', Account::TYPE_EQUITY)
            ->where(static function ($query): void {
                $query->where('name', 'LIKE', '%خلاصه%')
                    ->orWhere('name', 'LIKE', '%سود و زیان%')
                    ->orWhere('name', 'LIKE', '%income summary%');
            })
            ->orderBy('code')
            ->first(['id']);

        if ($fallback !== null) {
            return (int) $fallback->id;
        }

        // در صورت نبود حساب خلاصه، مسیر سود انباشته را به‌عنوان fallback می‌گیریم
        return $this->resolveRetainedEarningsAccountId();
    }
}
