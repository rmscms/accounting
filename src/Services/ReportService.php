<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{
    Account,
    AttendanceDaily,
    AttendancePeriod,
    Bank,
    CashBox,
    Cheque,
    Customer,
    AccountingDocument,
    Expense,
    FiscalYear,
    CustomerAdvance,
    CustomerInvoice,
    CustomerPayment,
    Employee,
    EmployeeContract,
    EmployeeLoan,
    EmployeeLoanInstallment,
    Supplier,
    SupplierInvoice,
    SupplierPayment,
    VatRemittance,
    FinancialLedger,
    CostEntry,
    Wallet
};
use RMS\Accounting\Services\FiscalYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use RMS\Accounting\Support\Reports\InsuranceMonthlyReportDto;
use RMS\Accounting\Services\Reports\Concerns\PartyReportsTrait;
use RMS\Accounting\Services\Reports\Concerns\StubReportResponses;

/**
 * سرویس گزارش‌های مالی
 * پیاده‌سازی استاندارد گزارش‌های حسابداری
 */
class ReportService
{
    use StubReportResponses;
    use PartyReportsTrait;

    private ?AccountingDateInputNormalizer $accountingDateNormalizer = null;

    protected function dateNormalizer(): AccountingDateInputNormalizer
    {
        return $this->accountingDateNormalizer ??= app(AccountingDateInputNormalizer::class);
    }

    /**
     * دریافت تاریخ شروع و پایان از فیلترها
     *
     * اولویت با from_date / to_date (مقدار اینپوت: میلادی ISO یا شمسی؛ میلادی 19xx/20xx مستقیم)
     * سازگاری با فیلترهای قدیمی: start_date، end_date
     */
    protected function getDateRange(array $filters): array
    {
        // پیش‌فرض بازه: سال مالی جاری تعریف‌شده؛ در نبود پرچم is_current همان منطق FiscalYearService (استاندارد دورهٔ گزارش‌دهی)
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        if ($fiscalYear === null) {
            try {
                $fiscalYear = app(FiscalYearService::class)->getOrCreateCurrentFiscalYear();
            } catch (\Throwable) {
                $fiscalYear = null;
            }
        }

        $norm = $this->dateNormalizer();

        $start = null;
        $end = null;

        if (! empty($filters['from_date'])) {
            $start = $norm->normalizeFilterDateToGregorian((string) $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $end = $norm->normalizeFilterDateToGregorian((string) $filters['to_date']);
        }

        if ($start === null && ! empty($filters['start_date'])) {
            $start = $norm->parseFlexibleDateFilter((string) $filters['start_date']);
        }
        if ($end === null && ! empty($filters['end_date'])) {
            $end = $norm->parseFlexibleDateFilter((string) $filters['end_date']);
        }

        if ($start === null) {
            $def = $fiscalYear ? $fiscalYear->start_date : Carbon::now()->startOfYear();
            $start = $def instanceof Carbon ? $def->format('Y-m-d') : (string) $def;
        }

        if ($end === null) {
            $def = $fiscalYear ? $fiscalYear->end_date : Carbon::now()->endOfYear();
            $end = $def instanceof Carbon ? $def->format('Y-m-d') : (string) $def;
        }

        if (is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start .= ' 00:00:00';
        }
        if (is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end .= ' 23:59:59';
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function normalizeAsOfDateToEndOfDay(string $date): string
    {
        $trimmed = trim($date);
        if ($trimmed === '') {
            return Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        }

        $parsed = Carbon::parse($trimmed);
        return $parsed->endOfDay()->format('Y-m-d H:i:s');
    }

    /**
     * بازهٔ پیش‌فرض مخصوص گزارش VAT:
     * - اگر کاربر فیلتر تاریخ داده باشد همان مبناست.
     * - در غیر این صورت: از فردای آخرین تسویهٔ VAT تا امروز.
     * - اگر تسویه‌ای نبود: از ابتدای دورهٔ مالی جاری (و در نبود آن، ابتدای ماه جاری) تا امروز.
     */
    protected function getVatDefaultDateRange(array $filters): array
    {
        if ($this->hasDateFilters($filters)) {
            return $this->getDateRange($filters);
        }

        $today = Carbon::today();
        $start = null;

        $lastRemittance = VatRemittance::query()
            ->where('status', VatRemittance::STATUS_POSTED)
            ->whereDate('payment_date', '<=', $today->toDateString())
            ->latest('payment_date')
            ->latest('id')
            ->first();

        if ($lastRemittance !== null && $lastRemittance->payment_date !== null) {
            $start = Carbon::parse((string) $lastRemittance->payment_date)
                ->addDay()
                ->startOfDay();
        }

        if ($start === null) {
            $fiscalYear = FiscalYear::where('is_current', true)->first();
            if ($fiscalYear === null) {
                try {
                    $fiscalYear = app(FiscalYearService::class)->getOrCreateCurrentFiscalYear();
                } catch (\Throwable) {
                    $fiscalYear = null;
                }
            }

            if ($fiscalYear !== null && $fiscalYear->start_date !== null) {
                $start = Carbon::parse((string) $fiscalYear->start_date)->startOfDay();
            } else {
                $start = Carbon::today()->startOfMonth()->startOfDay();
            }
        }

        if ($start->greaterThan($today)) {
            $start = $today->copy()->startOfDay();
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $today->copy()->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * آیا کاربر یکی از فیلدهای تاریخ را صراحتا ارسال کرده است؟
     */
    protected function hasDateFilters(array $filters): bool
    {
        foreach (['from_date', 'to_date', 'start_date', 'end_date'] as $key) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }
            if (trim((string) $filters[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * آخرین 10 دورهٔ تسویه VAT برای انتخاب سریع در فیلتر گزارش.
     *
     * @return array<int, array{from: string, to: string, label: string}>
     */
    protected function getVatQuickPeriods(int $limit = 10): array
    {
        $rows = VatRemittance::query()
            ->where('status', VatRemittance::STATUS_POSTED)
            ->whereNotNull('period_start')
            ->whereNotNull('period_end')
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit * 2)
            ->get(['period_start', 'period_end', 'payment_date']);

        $periods = [];
        $seen = [];

        foreach ($rows as $row) {
            $from = Carbon::parse((string) $row->period_start)->format('Y-m-d');
            $to = Carbon::parse((string) $row->period_end)->format('Y-m-d');
            $key = $from.'|'.$to;
            if (isset($seen[$key])) {
                continue;
            }

            $paymentDate = $row->payment_date
                ? Carbon::parse((string) $row->payment_date)->format('Y-m-d')
                : null;

            $periods[] = [
                'from' => $from,
                'to' => $to,
                'label' => $paymentDate !== null
                    ? sprintf('%s تا %s (تسویه: %s)', $from, $to, $paymentDate)
                    : sprintf('%s تا %s', $from, $to),
            ];

            $seen[$key] = true;
            if (count($periods) >= $limit) {
                break;
            }
        }

        return $periods;
    }

    /**
     * Resolve account filter from account_id/account_code/subsidiary_account_code inputs.
     *
     * @param  array<string, mixed>  $filters
     * @return array{account: ?Account, requested_code: string}
     */
    protected function resolveRequestedAccount(array $filters): array
    {
        $requestedCode = trim((string) ($filters['subsidiary_account_code'] ?? $filters['account_code'] ?? ''));
        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : 0;
        $account = null;

        if ($accountId > 0) {
            $account = Account::query()->find($accountId);
        } elseif ($requestedCode !== '') {
            $account = Account::query()
                ->where('code', $requestedCode)
                ->first();
        }

        return [
            'account' => $account,
            'requested_code' => $requestedCode,
        ];
    }

    // ==========================================
    // گزارش‌های مالی اصلی (Core Financial)
    // ==========================================

    /**
     * دفتر کل (General Ledger)
     * پیش‌فرض: درخت حساب‌ها — جمع زیرشاخه‌ها در والد؛ فقط ریشه‌ها در نما، جزئیات با باز کردن.
     * با فیلتر flat=1: لیست تخت همهٔ حساب‌های دارای گردش (رفتار قدیمی).
     */
    public function getGeneralLedger(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $accountFilter = $this->resolveRequestedAccount($filters);
        if ($accountFilter['account'] !== null) {
            $filters['account_id'] = (int) $accountFilter['account']->getKey();
        }

        if ($accountFilter['requested_code'] !== '' && $accountFilter['account'] === null && empty($filters['account_id'])) {
            return [
                'title' => 'دفتر کل',
                'error' => 'کد حساب واردشده یافت نشد.',
                'filter_account_code' => true,
                'selected_account_code' => $accountFilter['requested_code'],
            ];
        }

        $data = ! empty($filters['flat'])
            ? $this->getGeneralLedgerFlat($dateRange, $filters)
            : $this->getGeneralLedgerTree($dateRange, $filters);

        $data['treasury_negative_balances'] = $this->getTreasuryNegativeLedgerBalances($dateRange);
        $data['payroll_insurance_payable_summary'] = $this->getPayrollInsurancePayableSummary($dateRange);
        $data['filter_account_code'] = true;
        $data['selected_account_code'] = $accountFilter['account']?->code ?? $accountFilter['requested_code'];
        $data['selected_account_id'] = (int) ($accountFilter['account']?->id ?? ($filters['account_id'] ?? 0));

        return $data;
    }

    /**
     * خلاصهٔ تجمیعی بیمهٔ پرداختنی حقوق:
     * - سهم کارمند (2105)
     * - سهم کارفرما (2106)
     *
     * مانده‌ها تجمعی تا پایان دورهٔ انتخابی هستند تا با مفهوم «پرداختنی» سازگار باشند.
     *
     * @return array<string,mixed>|null
     */
    protected function getPayrollInsurancePayableSummary(array $dateRange): ?array
    {
        $employeeCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.employee_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.employee_insurance_payable', '2105'));
        $employerCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.employer_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.employer_insurance_payable', '2106'));
        $legacyCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.social_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.social_insurance_payable', '2104'));

        $codes = array_values(array_unique(array_filter([$employeeCode, $employerCode, $legacyCode])));
        if ($codes === []) {
            return null;
        }

        $accounts = Account::query()
            ->whereIn('code', $codes)
            ->where('active', true)
            ->get()
            ->keyBy('code');

        $sumRows = FinancialLedger::query()
            ->join('accounts', 'financial_ledgers.account_id', '=', 'accounts.id')
            ->whereIn('accounts.code', $codes)
            ->where('financial_ledgers.created_at', '<=', $dateRange['end'])
            ->selectRaw('accounts.code as code')
            ->selectRaw('SUM(financial_ledgers.debit_amount) as debit_sum')
            ->selectRaw('SUM(financial_ledgers.credit_amount) as credit_sum')
            ->groupBy('accounts.code')
            ->get()
            ->keyBy('code');

        $items = [];
        $total = 0.0;

        foreach ([$employeeCode, $employerCode] as $code) {
            /** @var Account|null $account */
            $account = $accounts->get($code);
            if (! $account) {
                continue;
            }
            $sum = $sumRows->get($code);
            $debit = (float) ($sum->debit_sum ?? 0.0);
            $credit = (float) ($sum->credit_sum ?? 0.0);
            $balance = $credit - $debit;

            $items[] = [
                'account_id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
            $total += $balance;
        }

        /** @var Account|null $legacyAccount */
        $legacyAccount = $accounts->get($legacyCode);
        $legacySum = $sumRows->get($legacyCode);
        $legacyDebit = (float) ($legacySum->debit_sum ?? 0.0);
        $legacyCredit = (float) ($legacySum->credit_sum ?? 0.0);

        return [
            'title' => trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.title'),
            'as_of' => $dateRange['end'],
            'total' => $total,
            'items' => $items,
            'legacy' => [
                'code' => $legacyAccount?->code ?? $legacyCode,
                'name' => $legacyAccount?->name,
                'debit' => $legacyDebit,
                'credit' => $legacyCredit,
                'balance' => $legacyCredit - $legacyDebit,
            ],
        ];
    }

    /**
     * بانک/صندوق‌های فعال با حساب دفترکل؛ ماندهٔ تجمعی دفتر تا پایان تاریخ «تا»ی گزارش.
     * اگر مانده از نظر طبیعت حساب منفی باشد، برای بنر هشدار برمی‌گردد.
     *
     * @return list<array{source_labels: string, account_code: string, account_name: string, balance: float, balance_formatted: string}>
     */
    protected function getTreasuryNegativeLedgerBalances(array $dateRange): array
    {
        $end = $dateRange['end'];

        /** @var array<int, list<array{kind: string, label: string}>> $sourcesByAccount */
        $sourcesByAccount = [];

        foreach (Bank::query()->where('active', true)->whereNotNull('account_id')->get() as $bank) {
            $aid = (int) $bank->account_id;
            if ($aid < 1) {
                continue;
            }
            $label = trim((string) ($bank->name ?? ''));
            if ($label === '') {
                $label = (string) trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.fallback_bank', ['id' => (int) $bank->id]);
            }
            $sourcesByAccount[$aid][] = ['kind' => 'bank', 'label' => $label];
        }

        foreach (CashBox::query()->where('active', true)->whereNotNull('account_id')->get() as $box) {
            $aid = (int) $box->account_id;
            if ($aid < 1) {
                continue;
            }
            $label = trim((string) ($box->name ?? ''));
            if ($label === '') {
                $label = (string) trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.fallback_cash_box', ['id' => (int) $box->id]);
            }
            $sourcesByAccount[$aid][] = ['kind' => 'cash_box', 'label' => $label];
        }

        if ($sourcesByAccount === []) {
            return [];
        }

        $accountIds = array_keys($sourcesByAccount);
        sort($accountIds);

        $sumRows = FinancialLedger::query()
            ->whereIn('account_id', $accountIds)
            ->where('created_at', '<=', $end)
            ->selectRaw('account_id, SUM(debit_amount) as debit_sum, SUM(credit_amount) as credit_sum')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $out = [];

        foreach ($accountIds as $aid) {
            $account = $accounts->get($aid);
            if ($account === null || ! $account->active) {
                continue;
            }

            $row = $sumRows->get($aid);
            $debit = $row ? (float) $row->debit_sum : 0.0;
            $credit = $row ? (float) $row->credit_sum : 0.0;

            $balance = $account->isDebitNormal()
                ? ($debit - $credit)
                : ($credit - $debit);

            if ($balance >= -0.009) {
                continue;
            }

            $sources = $sourcesByAccount[$aid] ?? [];
            $labels = [];
            foreach ($sources as $src) {
                $labels[] = $src['label'];
            }

            $out[] = [
                'source_labels' => implode('، ', array_unique($labels)),
                'account_code' => (string) ($account->code ?? ''),
                'account_name' => (string) ($account->name ?? ''),
                'balance' => $balance,
                'balance_formatted' => number_format($balance, 0, '.', ','),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['account_code'], $b['account_code']));

        return $out;
    }

    /**
     * دفتر کل — لیست تخت (هر حساب با گردش مستقیم، بدون تجمیع در والد)
     */
    protected function getGeneralLedgerFlat(array $dateRange, array $filters = []): array
    {
        $accountsQuery = Account::with('parent')
            ->where('active', true)
            ->orderBy('code');
        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : 0;
        if ($accountId > 0) {
            $accountsQuery->whereKey($accountId);
        }
        $accounts = $accountsQuery->get();
        $data = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $baseSums = $this->ledgerBaseDebitCreditSums(
                FinancialLedger::where('account_id', $account->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            );
            $debit = $baseSums['debit'];
            $credit = $baseSums['credit'];

            if ($debit > 0 || $credit > 0) {
                $balance = $account->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);

                $data[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'currency_code' => $account->currency_code,
                    'type' => trans('accounting::accounting.account_types.' . $account->account_type),
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                    'level' => $account->level,
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
            }
        }

        return [
            'title' => 'دفتر کل (همهٔ سطوح)',
            'period' => $dateRange,
            'accounts' => $data,
            'flat_list' => true,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
        ];
    }

    /**
     * جمع بدهکار/بستانکار مستقیم هر حساب در بازه
     *
     * @return array<int, array{debit: float, credit: float}>
     */
    protected function getLedgerDirectSumsByAccount(array $dateRange): array
    {
        $rows = FinancialLedger::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('account_id, SUM(CASE WHEN amount_base > 0 THEN amount_base ELSE 0 END) as debit_sum')
            ->selectRaw('SUM(CASE WHEN amount_base < 0 THEN ABS(amount_base) ELSE 0 END) as credit_sum')
            ->groupBy('account_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->account_id] = [
                'debit' => (float) $row->debit_sum,
                'credit' => (float) $row->credit_sum,
            ];
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Account>  $accounts
     * @return array<int, Account[]>
     */
    protected function buildChildrenByParentIndex(\Illuminate\Support\Collection $accounts): array
    {
        $childrenByParent = [];
        foreach ($accounts as $acc) {
            if ($acc->parent_id === null) {
                continue;
            }
            $pid = $acc->parent_id;
            if (! array_key_exists($pid, $childrenByParent)) {
                $childrenByParent[$pid] = [];
            }
            $childrenByParent[$pid][] = $acc;
        }
        foreach ($childrenByParent as &$list) {
            usort($list, fn (Account $a, Account $b) => strcmp($a->code, $b->code));
        }
        unset($list);

        return $childrenByParent;
    }

    /**
     * جمع بدهکار/بستانکار کل زیردرخت (شامل خود حساب)
     *
     * @param  array<int, array{debit: float, credit: float}>  $directMap
     * @param  array<int, Account[]>  $childrenByParent
     * @return array{debit: float, credit: float}
     */
    protected function rollupSubtreeTotals(Account $account, array $directMap, array $childrenByParent): array
    {
        $d = $directMap[$account->id]['debit'] ?? 0.0;
        $c = $directMap[$account->id]['credit'] ?? 0.0;
        foreach ($childrenByParent[$account->id] ?? [] as $child) {
            $sub = $this->rollupSubtreeTotals($child, $directMap, $childrenByParent);
            $d += $sub['debit'];
            $c += $sub['credit'];
        }

        return ['debit' => $d, 'credit' => $c];
    }

    /**
     * دفتر کل — درخت سبک: فقط حساب‌های ریشه با تجمیع؛ بدون زیرشاخه در DOM اولیه
     */
    protected function getGeneralLedgerTree(array $dateRange, array $filters = []): array
    {
        $directMap = $this->getLedgerDirectSumsByAccount($dateRange);

        $accounts = Account::query()
            ->where('active', true)
            ->orderBy('code')
            ->get();
        $childrenByParent = $this->buildChildrenByParentIndex($accounts);

        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : 0;
        if ($accountId > 0) {
            $focus = $accounts->firstWhere('id', $accountId);
            if (! $focus instanceof Account) {
                return [
                    'title' => 'دفتر کل',
                    'period' => $dateRange,
                    'accounts_tree' => [],
                    'lazy_branch' => false,
                    'totals' => [
                        'debit' => 0.0,
                        'credit' => 0.0,
                        'is_balanced' => true,
                    ],
                ];
            }
            $roots = collect([$focus]);
        } else {
            $roots = $accounts->filter(fn (Account $a) => $a->parent_id === null)->sortBy('code')->values();
        }

        $tree = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($roots as $root) {
            $rollup = $this->rollupSubtreeTotals($root, $directMap, $childrenByParent);
            $subDebit = $rollup['debit'];
            $subCredit = $rollup['credit'];

            $hasChildren = ! empty($childrenByParent[$root->id] ?? []);

            if ($subDebit < 0.000001 && $subCredit < 0.000001) {
                continue;
            }

            $balance = $root->isDebitNormal()
                ? ($subDebit - $subCredit)
                : ($subCredit - $subDebit);

            $tree[] = [
                'id' => $root->id,
                'code' => $root->code,
                'name' => $root->name,
                'currency_code' => $root->currency_code,
                'level' => $root->level,
                'type' => trans('accounting::accounting.account_types.' . $root->account_type),
                'subtree_debit' => $subDebit,
                'subtree_credit' => $subCredit,
                'balance' => $balance,
                'has_children' => $hasChildren,
            ];
            $totalDebit += $subDebit;
            $totalCredit += $subCredit;
        }

        return [
            'title' => 'دفتر کل',
            'period' => $dateRange,
            'accounts_tree' => $tree,
            'lazy_branch' => true,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
        ];
    }

    /**
     * یک سطح فرزندان حساب (برای AJAX گزارش دفتر کل)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGeneralLedgerBranchNodes(int $parentId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        $directMap = $this->getLedgerDirectSumsByAccount($dateRange);

        $accounts = Account::query()
            ->where('active', true)
            ->orderBy('code')
            ->get();
        $childrenByParent = $this->buildChildrenByParentIndex($accounts);

        if (! isset($childrenByParent[$parentId])) {
            return [];
        }

        $nodes = [];
        foreach ($childrenByParent[$parentId] as $child) {
            $rollup = $this->rollupSubtreeTotals($child, $directMap, $childrenByParent);
            $subDebit = $rollup['debit'];
            $subCredit = $rollup['credit'];

            if ($subDebit < 0.000001 && $subCredit < 0.000001) {
                continue;
            }

            $balance = $child->isDebitNormal()
                ? ($subDebit - $subCredit)
                : ($subCredit - $subDebit);

            $nodes[] = [
                'id' => $child->id,
                'code' => $child->code,
                'name' => $child->name,
                'currency_code' => $child->currency_code,
                'level' => $child->level,
                'type' => trans('accounting::accounting.account_types.' . $child->account_type),
                'subtree_debit' => $subDebit,
                'subtree_credit' => $subCredit,
                'balance' => $balance,
                'has_children' => ! empty($childrenByParent[$child->id] ?? []),
            ];
        }

        return $nodes;
    }

    /**
     * دفتر معین (Subsidiary Ledger)
     * جزئیات تراکنش‌های یک حساب خاص
     */
    public function getSubsidiaryLedger(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $accountFilter = $this->resolveRequestedAccount($filters);
        $account = $accountFilter['account'];

        if (! $account && $accountFilter['requested_code'] !== '') {
            return [
                'title' => 'دفتر معین',
                'error' => 'کد حساب واردشده یافت نشد.',
                'filter_account_code' => true,
                'selected_account_code' => $accountFilter['requested_code'],
            ];
        }
        if (! $account) {
            return [
                'title' => 'دفتر معین',
                'error' => 'لطفاً حساب یا کد تفصیلی را وارد کنید.',
                'filter_account_code' => true,
            ];
        }

        $entries = FinancialLedger::with('document')
            ->where('account_id', (int) $account->getKey())
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->orderBy('created_at')
            ->get();
        
        $data = [];
        $runningBalance = 0.0;
        
        foreach ($entries as $entry) {
            $delta = (float) $entry->debit_amount - (float) $entry->credit_amount;
            if ($account->isCreditNormal()) {
                $delta *= -1;
            }
            $runningBalance += $delta;
            
            $data[] = [
                'date' => $entry->created_at->format('Y-m-d'),
                'document_number' => $entry->document->document_number ?? '-',
                'description' => $entry->description,
                'debit' => $entry->debit_amount,
                'credit' => $entry->credit_amount,
                'balance' => $runningBalance,
            ];
        }

        return [
            'title' => 'دفتر معین - ' . $account->name,
            'account' => [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->account_type,
            ],
            'filter_account_code' => true,
            'selected_account_code' => (string) $account->code,
            'selected_account_id' => (int) $account->getKey(),
            'period' => $dateRange,
            'entries' => $data,
            'summary' => [
                'total_debit' => $entries->sum('debit_amount'),
                'total_credit' => $entries->sum('credit_amount'),
                'final_balance' => $runningBalance,
            ],
        ];
    }

    /**
     * تراز آزمایشی (Trial Balance)
     * بررسی تعادل دفتر کل
     */
    public function getTrialBalance(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $accountFilter = $this->resolveRequestedAccount($filters);
        if ($accountFilter['requested_code'] !== '' && $accountFilter['account'] === null && empty($filters['account_id'])) {
            return [
                'title' => 'تراز آزمایشی',
                'error' => 'کد حساب واردشده یافت نشد.',
                'filter_account_code' => true,
                'selected_account_code' => $accountFilter['requested_code'],
            ];
        }

        $accountsQuery = Account::where('active', true)
            ->whereIn('level', [2, 3]) // معین و تفصیلی
            ->orderBy('code');
        $accountId = (int) ($accountFilter['account']?->id ?? ($filters['account_id'] ?? 0));
        if ($accountId > 0) {
            $accountsQuery->whereKey($accountId);
        }
        $accounts = $accountsQuery->get();
        $data = [];
        $totalOpeningDebit = 0.0;
        $totalOpeningCredit = 0.0;
        $totalPeriodDebit = 0.0;
        $totalPeriodCredit = 0.0;
        $totalEndingDebit = 0.0;
        $totalEndingCredit = 0.0;
        
        foreach ($accounts as $account) {
            $openingSums = $this->ledgerBaseDebitCreditSums(
                FinancialLedger::query()
                    ->where('account_id', $account->id)
                    ->where('created_at', '<', $dateRange['start'])
            );
            $periodSums = $this->ledgerBaseDebitCreditSums(
                FinancialLedger::query()
                    ->where('account_id', $account->id)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            );

            $openingBalance = $account->isCreditNormal()
                ? ($openingSums['credit'] - $openingSums['debit'])
                : ($openingSums['debit'] - $openingSums['credit']);
            $endingDebitSum = $openingSums['debit'] + $periodSums['debit'];
            $endingCreditSum = $openingSums['credit'] + $periodSums['credit'];
            $endingBalance = $account->isCreditNormal()
                ? ($endingCreditSum - $endingDebitSum)
                : ($endingDebitSum - $endingCreditSum);

            if (
                abs($openingBalance) < 0.000001
                && abs($periodSums['debit']) < 0.000001
                && abs($periodSums['credit']) < 0.000001
                && abs($endingBalance) < 0.000001
            ) {
                continue;
            }

            $openingDebit = $openingBalance > 0 ? $openingBalance : 0.0;
            $openingCredit = $openingBalance < 0 ? abs($openingBalance) : 0.0;
            $endingDebit = $endingBalance > 0 ? $endingBalance : 0.0;
            $endingCredit = $endingBalance < 0 ? abs($endingBalance) : 0.0;

            $data[] = [
                'code' => $account->code,
                'name' => $account->name,
                'currency_code' => $account->currency_code,
                'opening_debit' => $openingDebit,
                'opening_credit' => $openingCredit,
                'period_debit' => $periodSums['debit'],
                'period_credit' => $periodSums['credit'],
                'ending_debit' => $endingDebit,
                'ending_credit' => $endingCredit,
            ];

            $totalOpeningDebit += $openingDebit;
            $totalOpeningCredit += $openingCredit;
            $totalPeriodDebit += $periodSums['debit'];
            $totalPeriodCredit += $periodSums['credit'];
            $totalEndingDebit += $endingDebit;
            $totalEndingCredit += $endingCredit;
        }
        
        return [
            'title' => 'تراز آزمایشی',
            'period' => $dateRange,
            'accounts' => $data,
            'trial_balance_extended' => true,
            'filter_account_code' => true,
            'selected_account_code' => $accountFilter['account']?->code ?? $accountFilter['requested_code'],
            'selected_account_id' => $accountId,
            'totals' => [
                'opening_debit' => $totalOpeningDebit,
                'opening_credit' => $totalOpeningCredit,
                'period_debit' => $totalPeriodDebit,
                'period_credit' => $totalPeriodCredit,
                'debit' => $totalEndingDebit,
                'credit' => $totalEndingCredit,
                'difference' => abs($totalEndingDebit - $totalEndingCredit),
                'is_balanced' => abs($totalEndingDebit - $totalEndingCredit) < 0.01,
            ],
        ];
    }

    /**
     * Convert mixed-currency ledger rows into base-currency debit/credit sums.
     *
     * @param \Illuminate\Database\Eloquent\Builder<FinancialLedger> $query
     * @return array{debit: float, credit: float}
     */
    protected function ledgerBaseDebitCreditSums(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $row = (clone $query)
            ->selectRaw('SUM(CASE WHEN amount_base > 0 THEN amount_base ELSE 0 END) as debit_sum')
            ->selectRaw('SUM(CASE WHEN amount_base < 0 THEN ABS(amount_base) ELSE 0 END) as credit_sum')
            ->first();

        return [
            'debit' => (float) ($row->debit_sum ?? 0.0),
            'credit' => (float) ($row->credit_sum ?? 0.0),
        ];
    }

    /**
     * ترازنامه (Balance Sheet)
     * دارایی‌ها = بدهی‌ها + حقوق صاحبان
     */
    public function getBalanceSheet(array $filters = []): array
    {
        $rawAsOf = $filters['as_of_date'] ?? null;
        $asOfDate = Carbon::now()->format('Y-m-d');
        if ($rawAsOf !== null && trim((string) $rawAsOf) !== '') {
            $parsed = $this->dateNormalizer()->normalizeFilterDateToGregorian(trim((string) $rawAsOf));
            if ($parsed !== null) {
                $asOfDate = $parsed;
            }
        }
        $asOfDateEnd = $this->normalizeAsOfDateToEndOfDay($asOfDate);
        
        // دارایی‌ها
        $assets = $this->getAccountsByType(Account::TYPE_ASSET, $asOfDateEnd);
        $totalAssets = array_sum(array_column($assets, 'balance'));
        
        // بدهی‌ها
        $liabilities = $this->getAccountsByType(Account::TYPE_LIABILITY, $asOfDateEnd);
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        
        // حقوق صاحبان
        $equity = $this->getAccountsByType(Account::TYPE_EQUITY, $asOfDateEnd);
        $totalEquity = array_sum(array_column($equity, 'balance'));
        
        // محاسبه سود/زیان انباشته از درآمد و هزینه
        $income = $this->getAccountsByType(Account::TYPE_INCOME, $asOfDateEnd);
        $totalIncome = array_sum(array_column($income, 'balance'));
        
        $expenses = $this->getAccountsByType(Account::TYPE_EXPENSE, $asOfDateEnd);
        $totalExpenses = array_sum(array_column($expenses, 'balance'));
        
        $retainedEarnings = $totalIncome - $totalExpenses;
        $totalEquity += $retainedEarnings;
        
        return [
            'title' => 'ترازنامه',
            'as_of_date' => $asOfDate,
            'as_of_datetime' => $asOfDateEnd,
            'assets' => [
                'items' => $assets,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'items' => $liabilities,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'items' => $equity,
                'retained_earnings' => $retainedEarnings,
                'total' => $totalEquity,
            ],
            'equation' => [
                'assets' => $totalAssets,
                'liabilities_plus_equity' => $totalLiabilities + $totalEquity,
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
            ],
        ];
    }

    /**
     * صورت سود و زیان (Income Statement)
     * درآمد - هزینه = سود قبل از مالیات → سود پس از مالیات
     */
    public function getIncomeStatement(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        // درآمدها
        $incomeAccounts = $this->getAccountsByType(Account::TYPE_INCOME, $dateRange['end'], $dateRange['start']);
        $totalRevenue = array_sum(array_column($incomeAccounts, 'balance'));
        
        // هزینه‌ها
        $expenseAccounts = $this->getAccountsByType(Account::TYPE_EXPENSE, $dateRange['end'], $dateRange['start']);
        $totalExpenses = array_sum(array_column($expenseAccounts, 'balance'));
        
        // تفکیک بهای تمام شده و هزینه‌های عملیاتی
        $cogs = 0;
        $operatingExpenses = 0;
        
        foreach ($expenseAccounts as $expense) {
            if (str_contains(strtolower($expense['name']), 'بهای') || 
                str_contains(strtolower($expense['name']), 'کالا') ||
                str_contains($expense['code'], '51')) {
                $cogs += $expense['balance'];
            } else {
                $operatingExpenses += $expense['balance'];
            }
        }
        
        $grossProfit = $totalRevenue - $cogs;
        $operatingIncome = $grossProfit - $operatingExpenses;
        $incomeBeforeTax = $totalRevenue - $totalExpenses;
        
        // ⭐ محاسبه مالیات بر درآمد (Income Tax) - استاندارد جهانی
        $incomeTaxRate = (float) \RMS\Core\Models\Setting::get('accounting.income_tax.rate', 25);
        $incomeTaxEnabled = (bool) \RMS\Core\Models\Setting::get('accounting.income_tax.enabled', false);
        $decimalPlaces = (int) \RMS\Core\Models\Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0));
        $decimalPlaces = min(4, max(0, $decimalPlaces));
        
        $incomeTaxExpense = 0;
        if ($incomeTaxEnabled && $incomeBeforeTax > 0) {
            $incomeTaxExpense = $incomeBeforeTax * ($incomeTaxRate / 100);
            $incomeTaxExpense = round($incomeTaxExpense, $decimalPlaces);
        }
        
        $netIncome = $incomeBeforeTax - $incomeTaxExpense;
        
        return [
            'title' => 'صورت سود و زیان',
            'period' => $dateRange,
            'revenue' => [
                'items' => $incomeAccounts,
                'total' => $totalRevenue,
            ],
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0,
            'operating_expenses' => [
                'total' => $operatingExpenses,
            ],
            'operating_income' => $operatingIncome,
            'income_before_tax' => $incomeBeforeTax, // ⭐ سود قبل از مالیات
            'income_tax_expense' => $incomeTaxExpense, // ⭐ هزینه مالیات بر درآمد
            'income_tax_rate' => $incomeTaxRate, // ⭐ نرخ مالیات
            'net_income' => $netIncome, // ⭐ سود خالص (پس از مالیات)
            'net_margin' => $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0,
        ];
    }

    /**
     * صورت جریان وجوه نقد (Cash Flow Statement)
     * فعالیت‌های عملیاتی، سرمایه‌گذاری و تامین مالی
     */
    public function getCashFlow(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        // شروع با سود خالص
        $incomeStatement = $this->getIncomeStatement($filters);
        $netIncome = $incomeStatement['net_income'];
        
        // فعالیت‌های عملیاتی
        $cashFromOperations = $netIncome;
        
        // تغییرات سرمایه در گردش
        $arPrefixes = $this->getCodePrefixesFromSetting('accounting.cash_flow.ar_prefixes', ['110']);
        $apPrefixes = $this->getCodePrefixesFromSetting('accounting.cash_flow.ap_prefixes', ['210']);
        $arChange = $this->getAccountBalanceChangeByPrefixes($arPrefixes, $dateRange);
        $apChange = $this->getAccountBalanceChangeByPrefixes($apPrefixes, $dateRange);
        
        $cashFromOperations = $cashFromOperations - $arChange + $apChange;
        
        // فعالیت‌های سرمایه‌گذاری (قابل تنظیم بر اساس کد حساب)
        $investingPrefixes = $this->getCodePrefixesFromSetting('accounting.cash_flow.investing_prefixes', ['12']);
        $investingActivities = $this->getAccountBalanceChangeByPrefixes($investingPrefixes, $dateRange);
        
        // فعالیت‌های تامین مالی (قابل تنظیم بر اساس کد حساب)
        $financingPrefixes = $this->getCodePrefixesFromSetting('accounting.cash_flow.financing_prefixes', ['21', '31']);
        $financingActivities = $this->getAccountBalanceChangeByPrefixes($financingPrefixes, $dateRange);
        
        $netCashChange = $cashFromOperations + $investingActivities + $financingActivities;

        return [
            'title' => 'صورت جریان وجوه نقد',
            'period' => $dateRange,
            'columns' => [
                'بخش',
                'شرح',
                'مبلغ',
            ],
            'rows' => [
                ['عملیاتی', 'سود خالص', $netIncome],
                ['عملیاتی', 'تغییر دریافتنی‌ها', $arChange],
                ['عملیاتی', 'تغییر پرداختنی‌ها', $apChange],
                ['عملیاتی', 'خالص جریان عملیاتی', $cashFromOperations],
                ['سرمایه‌گذاری', 'خالص جریان سرمایه‌گذاری', $investingActivities],
                ['تامین مالی', 'خالص جریان تامین مالی', $financingActivities],
                ['جمع', 'تغییر خالص وجه نقد', $netCashChange],
            ],
            'operating_activities' => [
                'net_income' => $netIncome,
                'ar_change' => $arChange,
                'ap_change' => $apChange,
                'net_cash' => $cashFromOperations,
            ],
            'investing_activities' => [
                'net_cash' => $investingActivities,
                'prefixes' => $investingPrefixes,
            ],
            'financing_activities' => [
                'net_cash' => $financingActivities,
                'prefixes' => $financingPrefixes,
            ],
            'net_change_in_cash' => $netCashChange,
        ];
    }

    /**
     * متد کمکی: دریافت حساب‌های یک نوع خاص
     */
    protected function getAccountsByType(string $type, string $endDate, ?string $startDate = null): array
    {
        $accounts = Account::where('account_type', $type)
            ->where('active', true)
            ->whereIn('level', [2, 3])
            ->orderBy('code')
            ->get();
        
        $data = [];
        
        foreach ($accounts as $account) {
            $query = FinancialLedger::where('account_id', $account->id)
                ->where('created_at', '<=', $endDate);
            
            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }
            
            $baseSums = $this->ledgerBaseDebitCreditSums($query);
            $debit = $baseSums['debit'];
            $credit = $baseSums['credit'];
            
            if ($debit > 0 || $credit > 0) {
                $balance = $account->isCreditNormal() ? ($credit - $debit) : ($debit - $credit);
                
                $data[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];
            }
        }
        
        return $data;
    }

    /**
     * متد کمکی: محاسبه تغییر مانده حساب
     */
    protected function getAccountBalanceChange(string $codePrefix, array $dateRange): float
    {
        return $this->getAccountBalanceChangeByPrefixes([$codePrefix], $dateRange);
    }

    /**
     * @param list<string> $prefixes
     */
    protected function getAccountBalanceChangeByPrefixes(array $prefixes, array $dateRange): float
    {
        $cleanPrefixes = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $prefixes), static fn ($v) => $v !== ''));
        if ($cleanPrefixes === []) {
            return 0.0;
        }

        $accounts = Account::query()
            ->where(function ($q) use ($cleanPrefixes) {
                foreach ($cleanPrefixes as $prefix) {
                    $q->orWhere('code', 'LIKE', $prefix . '%');
                }
            })
            ->pluck('id');
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $startSums = $this->ledgerBaseDebitCreditSums(
            FinancialLedger::query()
                ->whereIn('account_id', $accounts)
                ->where('created_at', '<', $dateRange['start'])
        );
        $endSums = $this->ledgerBaseDebitCreditSums(
            FinancialLedger::query()
                ->whereIn('account_id', $accounts)
                ->where('created_at', '<=', $dateRange['end'])
        );

        $startBalance = $startSums['debit'] - $startSums['credit'];
        $endBalance = $endSums['debit'] - $endSums['credit'];

        return $endBalance - $startBalance;
    }

    /**
     * @param list<string> $defaults
     * @return list<string>
     */
    protected function getCodePrefixesFromSetting(string $key, array $defaults): array
    {
        $raw = \RMS\Core\Models\Setting::get($key);
        if ($raw === null || trim((string) $raw) === '') {
            return $defaults;
        }

        $parts = preg_split('/[\s,;|]+/', (string) $raw) ?: [];
        $parts = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $parts), static fn ($v) => $v !== ''));

        return $parts === [] ? $defaults : $parts;
    }

    // ==========================================
    // گزارش‌های دریافتنی (AR)
    // ==========================================

    /**
     * حساب‌های دریافتنی کل
     */
    public function getAccountsReceivable(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = CustomerInvoice::with('customer')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->where('payment_status', '!=', CustomerInvoice::STATUS_PAID)
            ->get();

        $total = $invoices->sum('balance_due');
        
        return [
            'title' => 'حساب‌های دریافتنی',
            'period' => $dateRange,
            'total_receivable' => $total,
            'invoice_count' => $invoices->count(),
            'by_status' => [
                'unpaid' => $invoices->where('payment_status', CustomerInvoice::STATUS_UNPAID)->sum('balance_due'),
                'partially_paid' => $invoices->where('payment_status', CustomerInvoice::STATUS_PARTIALLY_PAID)->sum('balance_due'),
            ],
        ];
    }

    /**
     * مانده حساب مشتریان
     */
    public function getCustomerBalances(array $filters = []): array
    {
        $customers = Customer::with(['invoices' => function($q) {
            $q->where('payment_status', '!=', CustomerInvoice::STATUS_PAID);
        }])->get();
        
        $data = [];
        $totalBalance = 0;
        
        foreach ($customers as $customer) {
            $balance = $customer->invoices->sum('balance_due');
            
            if ($balance > 0) {
                $overdueAmount = $customer->invoices()
                    ->where('due_date', '<', Carbon::now())
                    ->where('payment_status', '!=', CustomerInvoice::STATUS_PAID)
                    ->sum('balance_due');
                
                $data[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'total_invoices' => $customer->invoices->count(),
                    'total_amount' => $customer->invoices->sum('total_amount'),
                    'paid_amount' => $customer->invoices->sum('paid_amount'),
                    'balance' => $balance,
                    'overdue_amount' => $overdueAmount,
                ];
                
                $totalBalance += $balance;
            }
        }

        return [
            'title' => 'مانده حساب مشتریان',
            'customers' => $data,
            'summary' => [
                'total_customers' => count($data),
                'total_balance' => $totalBalance,
                'overdue_balance' => array_sum(array_column($data, 'overdue_amount')),
            ],
        ];
    }

    /**
     * گردش حساب مشتری
     */
    public function getCustomerStatement(array $filters = []): array
    {
        $customerId = $filters['customer_id'] ?? null;
        $dateRange = $this->getDateRange($filters);
        
        if (!$customerId) {
            return ['title' => 'گردش حساب مشتری', 'error' => 'لطفاً مشتری را انتخاب کنید'];
        }
        
        $customer = Customer::find($customerId);
        if (!$customer) {
            return ['title' => 'گردش حساب مشتری', 'error' => 'مشتری یافت نشد'];
        }
        
        $invoices = $customer->invoices()
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('invoice_date')
            ->get();

        $payments = $customer->payments()
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', CustomerPayment::STATUS_COMPLETED)
            ->orderBy('payment_date')
            ->get();

        $advances = CustomerAdvance::query()
            ->where('customer_id', (int) $customerId)
            ->whereBetween('advance_date', [$dateRange['start'], $dateRange['end']])
            ->whereNotIn('status', [CustomerAdvance::STATUS_CANCELLED, CustomerAdvance::STATUS_REFUNDED])
            ->orderBy('advance_date')
            ->get();

        $entries = [];

        foreach ($invoices as $invoice) {
            $debit = $invoice->amount_base !== null ? (float) $invoice->amount_base : (float) $invoice->total_amount;
            $entries[] = [
                'date' => Carbon::parse((string) $invoice->invoice_date)->format('Y-m-d'),
                'document_number' => (string) ($invoice->invoice_number ?? ('INV-' . $invoice->id)),
                'description' => 'فاکتور فروش',
                'debit' => $debit,
                'credit' => 0.0,
                '_sort_at' => Carbon::parse((string) $invoice->invoice_date)->format('Y-m-d H:i:s'),
                '_priority' => 10,
                '_id' => (int) $invoice->id,
            ];
        }

        foreach ($payments as $payment) {
            $credit = $payment->amount_base !== null ? (float) $payment->amount_base : (float) $payment->amount;
            $entries[] = [
                'date' => Carbon::parse((string) $payment->payment_date)->format('Y-m-d'),
                'document_number' => (string) ($payment->payment_number ?? ('PAY-' . $payment->id)),
                'description' => 'دریافت نقدی مشتری',
                'debit' => 0.0,
                'credit' => $credit,
                '_sort_at' => Carbon::parse((string) $payment->payment_date)->format('Y-m-d H:i:s'),
                '_priority' => 20,
                '_id' => (int) $payment->id,
            ];
        }

        foreach ($advances as $advance) {
            $credit = $advance->amount_base !== null ? (float) $advance->amount_base : (float) $advance->amount;
            $entries[] = [
                'date' => Carbon::parse((string) $advance->advance_date)->format('Y-m-d'),
                'document_number' => (string) ($advance->advance_number ?? ('ADV-' . $advance->id)),
                'description' => 'پیش‌دریافت مشتری',
                'debit' => 0.0,
                'credit' => $credit,
                '_sort_at' => Carbon::parse((string) $advance->advance_date)->format('Y-m-d H:i:s'),
                '_priority' => 15,
                '_id' => (int) $advance->id,
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            $cmpDate = strcmp((string) ($left['_sort_at'] ?? ''), (string) ($right['_sort_at'] ?? ''));
            if ($cmpDate !== 0) {
                return $cmpDate;
            }

            $cmpPriority = ((int) ($left['_priority'] ?? 0)) <=> ((int) ($right['_priority'] ?? 0));
            if ($cmpPriority !== 0) {
                return $cmpPriority;
            }

            return ((int) ($left['_id'] ?? 0)) <=> ((int) ($right['_id'] ?? 0));
        });

        $runningBalance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $statementEntries = [];

        foreach ($entries as $entry) {
            $debit = (float) ($entry['debit'] ?? 0.0);
            $credit = (float) ($entry['credit'] ?? 0.0);
            $totalDebit += $debit;
            $totalCredit += $credit;
            $runningBalance += $debit - $credit;

            $statementEntries[] = [
                'date' => $entry['date'],
                'document_number' => $entry['document_number'],
                'description' => $entry['description'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'title' => 'گردش حساب مشتری - ' . $customer->name,
            'customer' => $customer,
            'period' => $dateRange,
            'invoices' => $invoices,
            'payments' => $payments,
            'advances' => $advances,
            'entries' => $statementEntries,
            'summary' => [
                'total_invoices' => $invoices->sum('total_amount'),
                'total_payments' => $payments->sum('amount'),
                'total_advances' => $advances->sum('amount'),
                'balance' => $runningBalance,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'final_balance' => $runningBalance,
            ],
        ];
    }

    /**
     * مشتریان معوق
     */
    public function getOverdueCustomers(array $filters = []): array
    {
        $overdueInvoices = CustomerInvoice::with('customer')
            ->where('due_date', '<', Carbon::now())
            ->where('payment_status', '!=', CustomerInvoice::STATUS_PAID)
            ->get()
            ->groupBy('customer_id');
        
        $data = [];
        
        foreach ($overdueInvoices as $customerId => $invoices) {
            $customer = $invoices->first()->customer;
            $overdueDays = Carbon::now()->diffInDays($invoices->min('due_date'));
            
            $data[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'overdue_invoices' => $invoices->count(),
                'overdue_amount' => $invoices->sum('balance_due'),
                'oldest_due_date' => $invoices->min('due_date'),
                'overdue_days' => $overdueDays,
            ];
        }
        
        // مرتب‌سازی بر اساس روزهای معوق
        usort($data, fn($a, $b) => $b['overdue_days'] - $a['overdue_days']);

        return [
            'title' => 'مشتریان معوق',
            'customers' => $data,
            'summary' => [
                'total_customers' => count($data),
                'total_overdue' => array_sum(array_column($data, 'overdue_amount')),
            ],
        ];
    }

    /**
     * تحلیل سنی دریافتنی‌ها (Aging Analysis)
     */
    public function getAgingAnalysisAR(array $filters = []): array
    {
        $invoices = CustomerInvoice::with('customer')
            ->where('payment_status', '!=', CustomerInvoice::STATUS_PAID)
            ->get();
        
        $aging = [
            'current' => 0,      // 0-30 روز
            '31_60' => 0,        // 31-60 روز
            '61_90' => 0,        // 61-90 روز
            'over_90' => 0,      // بیش از 90 روز
        ];
        
        $byCustomer = [];
        
        foreach ($invoices as $invoice) {
            $daysPastDue = Carbon::now()->diffInDays($invoice->due_date, false);
            $amount = $invoice->balance_due;
            
            $customerId = $invoice->customer_id;
            if (!isset($byCustomer[$customerId])) {
                $byCustomer[$customerId] = [
                    'customer' => $invoice->customer->name,
                    'current' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                ];
            }
            
            if ($daysPastDue <= 30) {
                $aging['current'] += $amount;
                $byCustomer[$customerId]['current'] += $amount;
            } elseif ($daysPastDue <= 60) {
                $aging['31_60'] += $amount;
                $byCustomer[$customerId]['31_60'] += $amount;
            } elseif ($daysPastDue <= 90) {
                $aging['61_90'] += $amount;
                $byCustomer[$customerId]['61_90'] += $amount;
            } else {
                $aging['over_90'] += $amount;
                $byCustomer[$customerId]['over_90'] += $amount;
            }
            
            $byCustomer[$customerId]['total'] += $amount;
        }
        
        return [
            'title' => 'تحلیل سنی دریافتنی‌ها',
            'aging_buckets' => $aging,
            'by_customer' => array_values($byCustomer),
            'total' => array_sum($aging),
        ];
    }

    public function getCustomerInvoicesHistory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = CustomerInvoice::with('customer')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('invoice_date', 'desc')
            ->get();

        return [
            'title' => 'تاریخچه فاکتورهای مشتری',
            'period' => $dateRange,
            'invoices' => $invoices,
            'summary' => [
                'count' => $invoices->count(),
                'total_amount' => $invoices->sum('total_amount'),
                'paid_amount' => $invoices->sum('paid_amount'),
                'balance_due' => $invoices->sum('balance_due'),
            ],
        ];
    }

    public function getPaymentsReceivedHistory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $payments = CustomerPayment::with('customer')
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('payment_date', 'desc')
            ->get();
        
        return [
            'title' => 'تاریخچه دریافت‌های نقدی',
            'period' => $dateRange,
            'payments' => $payments,
            'summary' => [
                'count' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
            ],
        ];
    }

    // ==========================================
    // گزارش‌های پرداختنی (AP)
    // ==========================================

    public function getAccountsPayable(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = SupplierInvoice::with('supplier')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->where('payment_status', '!=', 'paid')
            ->get();
        
        $total = $invoices->sum('balance_due');
        
        return [
            'title' => 'حساب‌های پرداختنی',
            'period' => $dateRange,
            'total_payable' => $total,
            'invoice_count' => $invoices->count(),
        ];
    }

    public function getSupplierBalances(array $filters = []): array
    {
        $suppliers = Supplier::with(['invoices' => function($q) {
            $q->where('payment_status', '!=', 'paid');
        }])->get();
        
        $data = [];
        $totalBalance = 0;
        
        foreach ($suppliers as $supplier) {
            $balance = $supplier->invoices->sum('balance_due');
            
            if ($balance > 0) {
                $data[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'balance' => $balance,
                ];
                
                $totalBalance += $balance;
            }
        }
        
        return [
            'title' => 'مانده حساب تامین‌کنندگان',
            'suppliers' => $data,
            'summary' => ['total_balance' => $totalBalance],
        ];
    }

    public function getSupplierStatement(array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        if (!$supplierId) {
            return ['title' => 'گردش حساب تامین‌کننده', 'error' => 'لطفاً تامین‌کننده را انتخاب کنید'];
        }
        
        $supplier = Supplier::find($supplierId);
        $dateRange = $this->getDateRange($filters);
        
        $invoices = $supplier->invoices()
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->get();
        
        return [
            'title' => 'گردش حساب تامین‌کننده - ' . $supplier->name,
            'supplier' => $supplier,
            'period' => $dateRange,
            'invoices' => $invoices,
            'summary' => [
                'total_invoices' => $invoices->sum('total_amount'),
                'balance' => $invoices->sum('balance_due'),
            ],
        ];
    }

    public function getOverduePayables(array $filters = []): array
    {
        $overdueInvoices = SupplierInvoice::with('supplier')
            ->where('due_date', '<', Carbon::now())
            ->where('payment_status', '!=', 'paid')
            ->get();
        
        return [
            'title' => 'بدهی‌های سررسید شده',
            'invoices' => $overdueInvoices,
            'total_overdue' => $overdueInvoices->sum('balance_due'),
        ];
    }

    public function getAgingAnalysisAP(array $filters = []): array
    {
        $invoices = SupplierInvoice::with('supplier')
            ->where('payment_status', '!=', 'paid')
            ->get();
        
        $aging = [
            'current' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'over_90' => 0,
        ];
        
        foreach ($invoices as $invoice) {
            $daysPastDue = Carbon::now()->diffInDays($invoice->due_date, false);
            $amount = $invoice->balance_due;
            
            if ($daysPastDue <= 30) $aging['current'] += $amount;
            elseif ($daysPastDue <= 60) $aging['31_60'] += $amount;
            elseif ($daysPastDue <= 90) $aging['61_90'] += $amount;
            else $aging['over_90'] += $amount;
        }
        
        return [
            'title' => 'تحلیل سنی پرداختنی‌ها',
            'aging_buckets' => $aging,
            'total' => array_sum($aging),
        ];
    }

    public function getPurchaseOrdersHistory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));

        $query = SupplierInvoice::query()
            ->with('supplier')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']]);

        if ($supplierId > 0) {
            $query->where('supplier_id', $supplierId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->get();
        $rows = $invoices->map(static function (SupplierInvoice $invoice): array {
            return [
                (string) ($invoice->invoice_number ?? ('SINV-'.$invoice->id)),
                (string) ($invoice->supplier?->name ?? '-'),
                optional($invoice->invoice_date)->format('Y-m-d'),
                optional($invoice->due_date)->format('Y-m-d'),
                (string) ($invoice->status ?? ''),
                round((float) ($invoice->total_amount ?? 0), 4),
                round((float) ($invoice->paid_amount ?? 0), 4),
                round((float) ($invoice->balance_due ?? 0), 4),
            ];
        })->all();

        return [
            'title' => 'تاریخچه سفارش/فاکتورهای خرید',
            'period' => $dateRange,
            'columns' => ['شماره', 'تامین‌کننده', 'تاریخ فاکتور', 'سررسید', 'وضعیت', 'مبلغ کل', 'پرداخت‌شده', 'مانده'],
            'rows' => $rows,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'filter_supplier' => true,
            'filter_status_options' => [
                'draft' => 'پیش‌نویس',
                'approved' => 'تاییدشده',
                'partially_paid' => 'پرداخت جزئی',
                'paid' => 'تسویه‌شده',
                'cancelled' => 'لغوشده',
            ],
            'filters' => [
                'supplier_id' => $supplierId,
                'status' => $status,
            ],
            'summary' => [
                'count' => $invoices->count(),
                'total_amount' => round((float) $invoices->sum('total_amount'), 4),
                'paid_amount' => round((float) $invoices->sum('paid_amount'), 4),
                'balance_due' => round((float) $invoices->sum('balance_due'), 4),
            ],
        ];
    }

    public function getSupplierInvoicesHistory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = SupplierInvoice::with('supplier')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->get();
        
        return [
            'title' => 'تاریخچه فاکتورهای خرید',
            'period' => $dateRange,
            'invoices' => $invoices,
            'total_amount' => $invoices->sum('total_amount'),
        ];
    }

    public function getPaymentsMadeHistory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $payments = SupplierPayment::with('supplier')
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->get();
        
        return [
            'title' => 'تاریخچه پرداخت‌های انجام شده',
            'period' => $dateRange,
            'payments' => $payments,
            'total_amount' => $payments->sum('amount'),
        ];
    }

    // ==========================================
    // گزارش‌های خزانه‌داری (Treasury)
    // ==========================================

    public function getBankBalances(array $filters = []): array
    {
        $banks = Bank::query()->where('active', true)->get();
        
        $data = [];
        $total = 0;
        
        foreach ($banks as $bank) {
            $accountId = (int) ($bank->account_id ?? 0);
            $ledgerBalance = $accountId > 0 ? $this->postedLedgerBalanceForAccount($accountId) : 0.0;
            $storedBalance = (float) $bank->balance;
            $data[] = [
                'id' => $bank->id,
                'name' => $bank->name,
                'account_number' => $bank->account_number,
                'balance' => $ledgerBalance,
                'stored_balance' => $storedBalance,
                'difference' => round($ledgerBalance - $storedBalance, 4),
            ];
            $total += $ledgerBalance;
        }
        
        return [
            'title' => 'موجودی بانک‌ها',
            'banks' => $data,
            'total' => $total,
        ];
    }

    public function getCashboxBalances(array $filters = []): array
    {
        $cashboxes = CashBox::query()->where('active', true)->get();
        
        $data = [];
        $total = 0;
        
        foreach ($cashboxes as $cashbox) {
            $accountId = (int) ($cashbox->account_id ?? 0);
            $ledgerBalance = $accountId > 0 ? $this->postedLedgerBalanceForAccount($accountId) : 0.0;
            $storedBalance = (float) $cashbox->balance;
            $data[] = [
                'id' => $cashbox->id,
                'name' => $cashbox->name,
                'balance' => $ledgerBalance,
                'stored_balance' => $storedBalance,
                'difference' => round($ledgerBalance - $storedBalance, 4),
            ];
            $total += $ledgerBalance;
        }
        
        return [
            'title' => 'موجودی صندوق‌ها',
            'cashboxes' => $data,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBankStatementShowData(int $bankId, array $filters = []): array
    {
        $bank = Bank::query()->with('account')->find($bankId);

        return $this->buildTreasuryStatementPayload('bank', $bank, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCashboxStatementShowData(int $cashboxId, array $filters = []): array
    {
        $cashbox = CashBox::query()->with('account')->find($cashboxId);

        return $this->buildTreasuryStatementPayload('cashbox', $cashbox, $filters);
    }

    public function getBankTransactions(array $filters = []): array
    {
        $title = trans('accounting::accounting.reports.bank_statement.title');
        $banks = Bank::query()->where('active', true)->orderBy('name')->get();

        $bankId = isset($filters['bank_id']) ? (int) $filters['bank_id'] : null;
        $mode = (string) ($filters['mode'] ?? 'detail');
        if (! in_array($mode, ['detail', 'summary'], true)) {
            $mode = 'detail';
        }

        $base = [
            'title' => $title,
            'period' => $this->getDateRange($filters),
            'banks' => $banks,
            'filter_bank' => true,
            'mode' => $mode,
            'bank_id' => $bankId,
        ];

        if ($bankId <= 0) {
            return array_merge($base, [
                'error' => trans('accounting::accounting.reports.bank_statement.select_bank'),
            ]);
        }

        $bank = Bank::query()->with('account')->find($bankId);
        if ($bank === null) {
            return array_merge($base, [
                'error' => trans('accounting::accounting.reports.bank_statement.bank_not_found'),
            ]);
        }

        if (! $bank->account_id) {
            return array_merge($base, [
                'bank' => $bank,
                'error' => trans('accounting::accounting.reports.bank_statement.bank_no_account'),
            ]);
        }

        $period = $this->getDateRange($filters);
        $accountId = (int) $bank->account_id;

        $docTime = static function ($prefix = 'ad'): string {
            return "COALESCE({$prefix}.posted_at, {$prefix}.created_at)";
        };

        $opening = (float) DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTime('ad').' < ?', [$period['start']])
            ->sum(DB::raw('fl.amount_base'));

        if ($mode === 'summary') {
            $summaryRows = $this->buildBankStatementSummaryRows($accountId, $period, $docTime);
            $running = $opening;
            foreach ($summaryRows as &$row) {
                $running += (float) ($row['net_change'] ?? 0);
                $row['running_balance'] = $running;
            }
            unset($row);

            return array_merge($base, [
                'bank' => $bank,
                'opening_balance' => $opening,
                'closing_balance' => $running,
                'summary_rows' => $summaryRows,
                'columns' => [
                    trans('accounting::accounting.reports.bank_statement.col_date'),
                    trans('accounting::accounting.reports.bank_statement.col_document'),
                    trans('accounting::accounting.reports.bank_statement.col_type'),
                    trans('accounting::accounting.reports.bank_statement.col_description'),
                    trans('accounting::accounting.reports.bank_statement.col_debit'),
                    trans('accounting::accounting.reports.bank_statement.col_credit'),
                    trans('accounting::accounting.reports.bank_statement.col_balance'),
                ],
            ]);
        }

        $lines = FinancialLedger::query()
            ->from('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTime('ad').' between ? and ?', [$period['start'], $period['end']])
            ->orderBy($docTime('ad'))
            ->orderBy('fl.id')
            ->select('fl.*')
            ->with(['document'])
            ->get();

        $running = $opening;
        $detailRows = [];
        foreach ($lines as $fl) {
            /** @var FinancialLedger $fl */
            $running += (float) $fl->amount_base;
            $doc = $fl->document;
            $posted = $doc ? ($doc->posted_at ?? $doc->created_at) : null;
            $detailRows[] = [
                'ledger_id' => $fl->id,
                'accounting_document_id' => $fl->accounting_document_id,
                'posted_at' => $posted,
                'document_number' => $doc ? $doc->document_number : '',
                'document_type' => $doc ? $doc->document_type : '',
                'description' => $fl->description ?: ($doc ? (string) $doc->description : ''),
                'debit_amount' => (float) $fl->debit_amount,
                'credit_amount' => (float) $fl->credit_amount,
                'amount_base' => (float) $fl->amount_base,
                'running_balance' => $running,
            ];
        }

        return array_merge($base, [
            'bank' => $bank,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'detail_rows' => $detailRows,
            'columns' => [
                trans('accounting::accounting.reports.bank_statement.col_date'),
                trans('accounting::accounting.reports.bank_statement.col_document'),
                trans('accounting::accounting.reports.bank_statement.col_type'),
                trans('accounting::accounting.reports.bank_statement.col_description'),
                trans('accounting::accounting.reports.bank_statement.col_debit'),
                trans('accounting::accounting.reports.bank_statement.col_credit'),
                trans('accounting::accounting.reports.bank_statement.col_balance'),
            ],
        ]);
    }

    /**
     * @param  callable(string): string  $docTime
     * @return array<int, array<string, mixed>>
     */
    protected function buildBankStatementSummaryRows(int $accountId, array $period, callable $docTime): array
    {
        $rows = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTime('ad').' between ? and ?', [$period['start'], $period['end']])
            ->groupBy('fl.accounting_document_id')
            ->orderByRaw('MIN('.$docTime('ad').') asc')
            ->orderBy('fl.accounting_document_id')
            ->select('fl.accounting_document_id')
            ->selectRaw('MIN('.$docTime('ad').') as doc_time')
            ->selectRaw('MAX(ad.document_number) as document_number')
            ->selectRaw('MAX(ad.document_type) as document_type')
            ->selectRaw('MAX(ad.description) as document_description')
            ->selectRaw('SUM(fl.debit_amount) as debit_amount')
            ->selectRaw('SUM(fl.credit_amount) as credit_amount')
            ->selectRaw('SUM(fl.amount_base) as net_change')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'accounting_document_id' => (int) $r->accounting_document_id,
                'posted_at' => $r->doc_time,
                'document_number' => (string) $r->document_number,
                'document_type' => (string) $r->document_type,
                'description' => (string) $r->document_description,
                'debit_amount' => (float) $r->debit_amount,
                'credit_amount' => (float) $r->credit_amount,
                'net_change' => (float) $r->net_change,
            ];
        }

        return $out;
    }

    /**
     * تمام خطوط دفتر برای یک سند (برای AJAX تفکیک سند)
     *
     * @return array{document: \RMS\Accounting\Models\AccountingDocument|null, lines: array<int, array<string, mixed>>}
     */
    public function getBankStatementDocumentLines(int $documentId): array
    {
        $document = AccountingDocument::query()->find($documentId);
        if ($document === null) {
            return ['document' => null, 'lines' => []];
        }

        $lines = FinancialLedger::query()
            ->where('accounting_document_id', $documentId)
            ->orderBy('id')
            ->with(['account'])
            ->get();

        $mapped = [];
        foreach ($lines as $line) {
            $mapped[] = [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account ? $line->account->code : '',
                'account_name' => $line->account ? $line->account->name : '',
                'debit_amount' => (float) $line->debit_amount,
                'credit_amount' => (float) $line->credit_amount,
                'amount_base' => (float) $line->amount_base,
                'description' => (string) ($line->description ?? ''),
                'event_type' => $line->event_type,
            ];
        }

        return [
            'document' => $document,
            'lines' => $mapped,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $detailRows
     * @return array<int, array<int, mixed>>
     */
    public function bankStatementDetailExcelRows(array $detailRows): array
    {
        $rows = [];
        foreach ($detailRows as $r) {
            $posted = $r['posted_at'] ?? null;
            $dateStr = '';
            if ($posted !== null) {
                try {
                    $dateStr = Carbon::parse($posted)->format('Y-m-d H:i');
                } catch (\Throwable) {
                    $dateStr = (string) $posted;
                }
            }
            $rows[] = [
                $dateStr,
                $r['document_number'] ?? '',
                $r['document_type'] ?? '',
                $r['description'] ?? '',
                (float) ($r['debit_amount'] ?? 0),
                (float) ($r['credit_amount'] ?? 0),
                (float) ($r['running_balance'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $summaryRows
     * @return array<int, array<int, mixed>>
     */
    public function bankStatementSummaryExcelRows(array $summaryRows): array
    {
        $rows = [];
        foreach ($summaryRows as $r) {
            $posted = $r['posted_at'] ?? null;
            $dateStr = '';
            if ($posted !== null) {
                try {
                    $dateStr = Carbon::parse($posted)->format('Y-m-d H:i');
                } catch (\Throwable) {
                    $dateStr = (string) $posted;
                }
            }
            $rows[] = [
                $dateStr,
                $r['document_number'] ?? '',
                $r['document_type'] ?? '',
                $r['description'] ?? '',
                (float) ($r['debit_amount'] ?? 0),
                (float) ($r['credit_amount'] ?? 0),
                (float) ($r['running_balance'] ?? 0),
            ];
        }

        return $rows;
    }

    public function getCashTransactions(array $filters = []): array
    {
        $cashboxId = isset($filters['cashbox_id']) ? (int) $filters['cashbox_id'] : 0;
        $cashboxes = CashBox::query()->where('active', true)->orderBy('name')->get();
        $payload = $this->getCashboxStatementShowData($cashboxId, $filters);

        return [
            'title' => trans('accounting::accounting.reports.treasury_statement.cashbox_title'),
            'cashboxes' => $cashboxes,
            'cashbox_id' => $cashboxId,
            'period' => $payload['period'] ?? $this->getDateRange($filters),
            'statement' => $payload,
            'error' => $payload['error'] ?? null,
        ];
    }

    public function getChequesReceived(array $filters = []): array
    {
        $cheques = Cheque::where('cheque_type', 'received')
            ->orderBy('due_date')
            ->get();
        
        $summary = [
            'pending' => ['count' => 0, 'amount' => 0],
            'cashed' => ['count' => 0, 'amount' => 0],
            'bounced' => ['count' => 0, 'amount' => 0],
        ];
        
        foreach ($cheques as $cheque) {
            $summary[$cheque->status]['count']++;
            $summary[$cheque->status]['amount'] += $cheque->amount;
        }
        
        return [
            'title' => 'چک‌های دریافتی',
            'cheques' => $cheques,
            'summary' => $summary,
        ];
    }

    public function getChequesIssued(array $filters = []): array
    {
        $cheques = Cheque::where('cheque_type', 'issued')
            ->orderBy('due_date')
            ->get();
        
        return [
            'title' => 'چک‌های پرداختی',
            'cheques' => $cheques,
            'total_amount' => $cheques->sum('amount'),
        ];
    }

    public function getChequeReminders(array $filters = []): array
    {
        $upcomingCheques = Cheque::where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('due_date')
            ->get();
        
        return [
            'title' => 'چک‌های سررسید',
            'cheques' => $upcomingCheques,
            'count' => $upcomingCheques->count(),
        ];
    }

    public function getPOSReport(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.pos_report');
    }

    public function getWalletReport(array $filters = []): array
    {
        $walletType = isset($filters['wallet_type']) ? trim((string) $filters['wallet_type']) : '';
        $currencyCode = isset($filters['currency_code']) ? strtoupper(trim((string) $filters['currency_code'])) : '';

        $q = Wallet::query()->with(['account']);
        if ($walletType !== '') {
            $q->where('wallet_type', $walletType);
        }
        if ($currencyCode !== '') {
            $q->where('currency_code', $currencyCode);
        }

        $wallets = $q->orderBy('id')->get();

        $columns = [
            trans('accounting::accounting.reports.wallet_report.col_id'),
            trans('accounting::accounting.reports.wallet_report.col_type'),
            trans('accounting::accounting.reports.wallet_report.col_currency'),
            trans('accounting::accounting.reports.wallet_report.col_stored'),
            trans('accounting::accounting.reports.wallet_report.col_ledger'),
            trans('accounting::accounting.reports.wallet_report.col_diff'),
            trans('accounting::accounting.reports.wallet_report.col_account'),
        ];

        $rows = [];
        foreach ($wallets as $w) {
            $accountId = (int) $w->account_id;
            $ledgerNet = 0.0;
            if ($accountId > 0) {
                $ledgerNet = $this->postedLedgerBalanceForAccount($accountId);
            }
            $stored = (float) $w->balance;
            $diff = round($stored - $ledgerNet, 4);

            $rows[] = [
                $w->id,
                (string) $w->wallet_type,
                (string) $w->currency_code,
                $stored,
                $ledgerNet,
                $diff,
                (string) ($w->account?->code ?? '-'),
            ];
        }

        return [
            'title' => trans('accounting::accounting.reports.wallet_report.title'),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    protected function postedLedgerBalanceForAccount(int $accountId): float
    {
        if ($accountId < 1) {
            return 0.0;
        }

        return (float) DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->sum('fl.amount_base');
    }

    protected function postedDocumentTimeExpr(string $docAlias = 'ad'): string
    {
        return "COALESCE({$docAlias}.posted_at, {$docAlias}.created_at)";
    }

    /**
     * @param  Bank|CashBox|null  $endpoint
     * @return array<string, mixed>
     */
    protected function buildTreasuryStatementPayload(string $type, $endpoint, array $filters = []): array
    {
        $period = $this->getDateRange($filters);
        $perPage = min(200, max(10, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $eventType = strtoupper(trim((string) ($filters['event_type'] ?? '')));
        $eventSource = strtolower(trim((string) ($filters['event_source'] ?? '')));
        $search = trim((string) ($filters['q'] ?? ''));
        $docSource = strtolower(trim((string) ($filters['doc_source'] ?? '')));

        if ($endpoint === null) {
            return [
                'type' => $type,
                'period' => $period,
                'error' => trans('accounting::accounting.reports.treasury_statement.endpoint_not_found'),
            ];
        }

        $accountId = (int) ($endpoint->account_id ?? 0);
        if ($accountId < 1) {
            return [
                'type' => $type,
                'endpoint' => $endpoint,
                'period' => $period,
                'error' => trans('accounting::accounting.reports.treasury_statement.endpoint_no_account'),
            ];
        }

        $docTimeExpr = $this->postedDocumentTimeExpr('ad');

        $applyLineFilters = function ($query) use ($eventType, $eventSource, $docSource, $search): void {
            if ($eventType !== '') {
                $query->where('fl.event_type', $eventType);
            }
            if ($eventSource !== '') {
                $query->where('fl.event_source', $eventSource);
            }
            if ($docSource !== '') {
                $query->where('ad.reference_type', $docSource);
            }
            if ($search !== '') {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('fl.description', 'like', '%'.$search.'%')
                        ->orWhere('ad.description', 'like', '%'.$search.'%')
                        ->orWhere('ad.document_number', 'like', '%'.$search.'%');
                });
            }
        };

        $openingQuery = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTimeExpr.' < ?', [$period['start']]);
        $applyLineFilters($openingQuery);
        $openingBalance = (float) $openingQuery->sum('fl.amount_base');

        $baseQuery = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTimeExpr.' between ? and ?', [$period['start'], $period['end']]);
        $applyLineFilters($baseQuery);

        $totalRows = (int) (clone $baseQuery)->count('fl.id');
        $rawRows = (clone $baseQuery)
            ->orderByRaw($docTimeExpr.' asc')
            ->orderBy('fl.id')
            ->forPage($page, $perPage)
            ->select([
                'fl.id',
                'fl.event_type',
                'fl.event_source',
                'fl.description as ledger_description',
                'fl.debit_amount',
                'fl.credit_amount',
                'fl.amount_base',
                'fl.accounting_document_id',
                'ad.document_number',
                'ad.document_type',
                'ad.reference_type',
                'ad.description as document_description',
            ])
            ->selectRaw($docTimeExpr.' as posted_at')
            ->get();

        $running = $openingBalance;
        $rows = [];
        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        foreach ($rawRows as $row) {
            $net = (float) ($row->amount_base ?? 0);
            if ($net >= 0) {
                $totalInflow += $net;
            } else {
                $totalOutflow += abs($net);
            }
            $running += $net;

            $rows[] = [
                'ledger_id' => (int) $row->id,
                'posted_at' => $row->posted_at,
                'document_number' => (string) ($row->document_number ?? ''),
                'document_type' => (string) ($row->document_type ?? ''),
                'doc_source' => (string) ($row->reference_type ?? ''),
                'event_type' => (string) ($row->event_type ?? ''),
                'event_source' => (string) ($row->event_source ?? ''),
                'description' => (string) ($row->ledger_description ?: $row->document_description),
                'debit_amount' => (float) ($row->debit_amount ?? 0),
                'credit_amount' => (float) ($row->credit_amount ?? 0),
                'amount_base' => $net,
                'running_balance' => $running,
                'accounting_document_id' => (int) ($row->accounting_document_id ?? 0),
            ];
        }

        $paginator = new LengthAwarePaginator(
            $rows,
            $totalRows,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $ledgerPostedBalance = $this->postedLedgerBalanceForAccount($accountId);
        $storedBalance = (float) ($endpoint->balance ?? 0);

        return [
            'type' => $type,
            'endpoint' => $endpoint,
            'period' => $period,
            'filters' => [
                'event_type' => $eventType,
                'event_source' => $eventSource,
                'doc_source' => $docSource,
                'q' => $search,
                'per_page' => $perPage,
            ],
            'summary' => [
                'opening_balance' => $openingBalance,
                'closing_balance' => $running,
                'total_inflow' => $totalInflow,
                'total_outflow' => $totalOutflow,
                'transaction_count' => $totalRows,
            ],
            'diagnostics' => [
                'ledger_posted_balance' => $ledgerPostedBalance,
                'stored_balance' => $storedBalance,
                'difference' => round($ledgerPostedBalance - $storedBalance, 4),
                'needs_sync' => abs($ledgerPostedBalance - $storedBalance) > 0.0001,
            ],
            'rows' => $rows,
            'paginator' => $paginator,
        ];
    }

    // ==========================================
    // گزارش‌های مالیاتی (Tax)
    // ==========================================

    public function getVATReport(array $filters = []): array
    {
        $dateRange = $this->getVatDefaultDateRange($filters);

        $vatPayload = app(TaxService::class)->getTaxSettings()['vat'] ?? [];

        $salesInvoices = CustomerInvoice::query()
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->where('status', CustomerInvoice::STATUS_ISSUED)
            ->with(['customer:id,name', 'items:id,customer_invoice_id,tax_rate'])
            ->get();
        $outputVAT = $salesInvoices->sum('tax_amount');

        $purchaseInvoices = SupplierInvoice::query()
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('document_id')
            ->with(['supplier:id,name', 'items:id,supplier_invoice_id,tax_rate'])
            ->get();
        $inputVAT = $purchaseInvoices->sum('tax_amount');

        $vatPayable = $outputVAT - $inputVAT;
        $remittedVat = (float) VatRemittance::query()
            ->where('status', VatRemittance::STATUS_POSTED)
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');
        $netPayableRemaining = (float) ($vatPayable - $remittedVat);

        $transactionType = (string) ($filters['transaction_type'] ?? 'all');
        $rows = [];
        $columns = [
            'نوع',
            'شماره سند',
            'تاریخ',
            'طرف حساب',
            'مبنای مالیات',
            'نرخ',
            'الگوی نرخ',
            'VAT',
            'وضعیت',
        ];

        $resolveRateMeta = static function ($invoice, string $itemsRelation, float $fallbackRate): array {
            $items = $invoice->{$itemsRelation} ?? collect();
            $rates = collect($items)
                ->map(static fn ($item) => round((float) ($item->tax_rate ?? $fallbackRate), 4))
                ->filter(static fn ($rate) => $rate >= 0)
                ->unique()
                ->values();

            if ($rates->isEmpty()) {
                return [
                    'rate' => $fallbackRate,
                    'mode' => 'default',
                ];
            }
            if ($rates->count() === 1) {
                return [
                    'rate' => (float) $rates->first(),
                    'mode' => 'single',
                ];
            }

            return [
                'rate' => 'mixed',
                'mode' => 'mixed',
            ];
        };

        if ($transactionType === 'all' || $transactionType === 'sales') {
            foreach ($salesInvoices as $invoice) {
                $rateMeta = $resolveRateMeta($invoice, 'items', (float) ($vatPayload['rate'] ?? 9));
                $rows[] = [
                    'فروش',
                    (string) ($invoice->invoice_number ?? ('INV-'.$invoice->id)),
                    (string) ($invoice->invoice_date ?? ''),
                    (string) ($invoice->customer?->name ?? $invoice->customer_id ?? '-'),
                    (float) ($invoice->subtotal ?? 0),
                    $rateMeta['rate'],
                    $rateMeta['mode'],
                    (float) ($invoice->tax_amount ?? 0),
                    (string) ($invoice->status ?? ''),
                ];
            }
        }

        if ($transactionType === 'all' || $transactionType === 'purchases') {
            foreach ($purchaseInvoices as $invoice) {
                $rateMeta = $resolveRateMeta($invoice, 'items', (float) ($vatPayload['rate'] ?? 9));
                $rows[] = [
                    'خرید',
                    (string) ($invoice->invoice_number ?? ('PINV-'.$invoice->id)),
                    (string) ($invoice->invoice_date ?? ''),
                    (string) ($invoice->supplier?->name ?? $invoice->supplier_id ?? '-'),
                    (float) ($invoice->subtotal ?? 0),
                    $rateMeta['rate'],
                    $rateMeta['mode'],
                    (float) ($invoice->tax_amount ?? 0),
                    (string) ($invoice->status ?? ''),
                ];
            }
        }

        return [
            'title' => 'گزارش مالیات بر ارزش افزوده',
            'period' => $dateRange,
            'settings' => [
                'vat_rate' => $vatPayload['rate'] ?? 9,
                'method' => $vatPayload['method'] ?? 'exclusive',
                'enabled' => $vatPayload['enabled'] ?? true,
            ],
            'output_vat' => [
                'sales' => $salesInvoices->sum('subtotal'),
                'vat' => $outputVAT,
                'vat_rate' => $vatPayload['rate'] ?? 9,
            ],
            'input_vat' => [
                'purchases' => $purchaseInvoices->sum('subtotal'),
                'vat' => $inputVAT,
            ],
            'vat_payable' => $vatPayable,
            'remitted_vat' => $remittedVat,
            'net_payable_remaining' => $netPayableRemaining,
            'vat_quick_periods' => $this->getVatQuickPeriods(10),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    public function getVATPayable(array $filters = []): array
    {
        $vatReport = $this->getVATReport($filters);
        return [
            'title' => 'مالیات قابل پرداخت',
            'vat_payable' => $vatReport['vat_payable'],
            'remitted_vat' => $vatReport['remitted_vat'] ?? 0,
            'net_payable_remaining' => $vatReport['net_payable_remaining'] ?? ($vatReport['vat_payable'] ?? 0),
        ];
    }

    public function getVATReceivable(array $filters = []): array
    {
        $vatReport = $this->getVATReport($filters);
        $receivable = ($vatReport['net_payable_remaining'] ?? $vatReport['vat_payable']) < 0
            ? abs((float) ($vatReport['net_payable_remaining'] ?? $vatReport['vat_payable']))
            : 0;
        
        return [
            'title' => 'مالیات قابل دریافت',
            'vat_receivable' => $receivable,
        ];
    }

    public function getIncomeTaxReport(array $filters = []): array
    {
        $incomeStatement = $this->getIncomeStatement($filters);
        $dateRange = $this->getDateRange($filters);

        $revenue = round((float) data_get($incomeStatement, 'revenue.total', 0), 4);
        $costOfGoodsSold = round((float) data_get($incomeStatement, 'cost_of_goods_sold', 0), 4);
        $operatingExpenses = round((float) data_get($incomeStatement, 'operating_expenses.total', 0), 4);
        $financialExpenses = round((float) data_get($incomeStatement, 'financial_expenses.total', 0), 4);
        $otherExpenses = round((float) data_get($incomeStatement, 'other_expenses.total', 0), 4);
        $deductible = round($costOfGoodsSold + $operatingExpenses + $financialExpenses + $otherExpenses, 4);
        $taxableIncome = round(max(0, $revenue - $deductible), 4);
        $taxRate = (float) \RMS\Core\Models\Setting::get('accounting.tax.income_tax_rate_percent', 25);
        $taxRate = $taxRate > 1 ? ($taxRate / 100) : $taxRate;
        $estimatedTax = round($taxableIncome * $taxRate, 4);

        return [
            'title' => 'برآورد مالیات بر درآمد',
            'period' => $dateRange,
            'tax_rate' => $taxRate,
            'columns' => ['شرح', 'مبلغ'],
            'rows' => [
                ['درآمد مشمول', $revenue],
                ['جمع هزینه‌های قابل کسر', $deductible],
                ['سود مشمول مالیات', $taxableIncome],
                ['نرخ مالیات', round($taxRate * 100, 2)],
                ['مالیات برآوردی', $estimatedTax],
            ],
            'summary' => [
                'revenue' => $revenue,
                'deductible_expenses' => $deductible,
                'taxable_income' => $taxableIncome,
                'estimated_tax' => $estimatedTax,
            ],
        ];
    }

    public function getTaxableTransactions(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);

        $sales = CustomerInvoice::query()
            ->with('customer')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->where('tax_amount', '>', 0)
            ->orderByDesc('invoice_date')
            ->get();

        $purchases = SupplierInvoice::query()
            ->with('supplier')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->where('tax_amount', '>', 0)
            ->orderByDesc('invoice_date')
            ->get();

        $rows = [];
        foreach ($sales as $invoice) {
            $rows[] = [
                'نوع' => 'فروش',
                'شماره' => (string) ($invoice->invoice_number ?? ('CINV-'.$invoice->id)),
                'تاریخ' => optional($invoice->invoice_date)->format('Y-m-d'),
                'طرف حساب' => (string) ($invoice->customer?->name ?? '-'),
                'مبلغ مبنا' => round((float) ($invoice->subtotal ?? 0), 4),
                'مالیات' => round((float) ($invoice->tax_amount ?? 0), 4),
                'جمع' => round((float) ($invoice->total_amount ?? 0), 4),
            ];
        }
        foreach ($purchases as $invoice) {
            $rows[] = [
                'نوع' => 'خرید',
                'شماره' => (string) ($invoice->invoice_number ?? ('SINV-'.$invoice->id)),
                'تاریخ' => optional($invoice->invoice_date)->format('Y-m-d'),
                'طرف حساب' => (string) ($invoice->supplier?->name ?? '-'),
                'مبلغ مبنا' => round((float) ($invoice->subtotal ?? 0), 4),
                'مالیات' => round((float) ($invoice->tax_amount ?? 0), 4),
                'جمع' => round((float) ($invoice->total_amount ?? 0), 4),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['تاریخ'] ?? ''), (string) ($a['تاریخ'] ?? ''));
        });

        $sumBase = array_sum(array_map(static fn (array $row): float => (float) ($row['مبلغ مبنا'] ?? 0), $rows));
        $sumTax = array_sum(array_map(static fn (array $row): float => (float) ($row['مالیات'] ?? 0), $rows));
        $sumTotal = array_sum(array_map(static fn (array $row): float => (float) ($row['جمع'] ?? 0), $rows));

        return [
            'title' => 'تراکنش‌های مشمول مالیات',
            'period' => $dateRange,
            'columns' => ['نوع', 'شماره', 'تاریخ', 'طرف حساب', 'مبلغ مبنا', 'مالیات', 'جمع'],
            'rows' => array_map(static fn (array $row): array => [
                $row['نوع'],
                $row['شماره'],
                $row['تاریخ'],
                $row['طرف حساب'],
                $row['مبلغ مبنا'],
                $row['مالیات'],
                $row['جمع'],
            ], $rows),
            'summary' => [
                'count' => count($rows),
                'base_amount' => round((float) $sumBase, 4),
                'tax_amount' => round((float) $sumTax, 4),
                'total_amount' => round((float) $sumTotal, 4),
            ],
        ];
    }

    // ==========================================
    // گزارش‌های هزینه (Expense)
    // ==========================================

    public function getExpenseSummary(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $expenses = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])->get();
        
        return [
            'title' => 'خلاصه هزینه‌ها',
            'period' => $dateRange,
            'total_expenses' => $expenses->sum('total_amount'),
            'count' => $expenses->count(),
            'by_status' => [
                'approved' => $expenses->where('status', 'approved')->sum('total_amount'),
                'pending' => $expenses->where('status', 'pending')->sum('total_amount'),
            ],
        ];
    }

    public function getExpenseMonthly(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $expenses = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('YEAR(expense_date) as year, MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        return [
            'title' => 'هزینه‌های ماهانه',
            'period' => $dateRange,
            'monthly_data' => $expenses,
        ];
    }

    public function getExpenseByCategory(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $expenses = Expense::with('category')
            ->whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->groupBy('category_id');
        
        $total = 0;
        $data = [];
        
        foreach ($expenses as $categoryId => $categoryExpenses) {
            $amount = $categoryExpenses->sum('total_amount');
            $total += $amount;
            
            $data[] = [
                'category' => $categoryExpenses->first()->category->name ?? 'بدون دسته',
                'amount' => $amount,
            ];
        }
        
        // اضافه کردن درصد
        foreach ($data as &$item) {
            $item['percent'] = $total > 0 ? ($item['amount'] / $total) * 100 : 0;
        }
        
        return [
            'title' => 'هزینه به تفکیک دسته',
            'period' => $dateRange,
            'categories' => $data,
            'total' => $total,
        ];
    }

    public function getRecurringExpenses(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.recurring_expenses');
    }

    public function getExpenseVsBudget(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.expense_vs_budget');
    }

    public function getTopExpenses(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $limit = $filters['limit'] ?? 10;
        
        $expenses = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('amount', 'desc')
            ->limit($limit)
            ->get();
        
        return [
            'title' => 'بزرگ‌ترین هزینه‌ها',
            'period' => $dateRange,
            'expenses' => $expenses,
            'total' => $expenses->sum('total_amount'),
        ];
    }

    // ==========================================
    // گزارش‌های ارزی (Currency/FX)
    // ==========================================

    public function getCurrencyTransactions(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.currency_transactions');
    }

    public function getFXGainLoss(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.fx_gain_loss');
    }

    public function getFXRatesUsed(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.fx_rates_used');
    }

    public function getForeignPurchases(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.foreign_purchases');
    }

    public function getCurrencyBalances(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.currency_balances');
    }

    // ==========================================
    // گزارش‌های COGS
    // ==========================================

    public function getCOGSReport(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.cogs_report');
    }

    public function getProductProfitability(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.product_profitability');
    }

    public function getSalesVsCOGS(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.sales_vs_cogs');
    }

    public function getCOGSMonthlyTrend(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);

        $rows = FinancialLedger::query()
            ->join('accounts', 'accounts.id', '=', 'financial_ledgers.account_id')
            ->whereBetween('financial_ledgers.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('accounts.account_type', Account::TYPE_EXPENSE)
            ->where(function ($q) {
                $q->where('accounts.code', 'LIKE', '51%')
                    ->orWhere('accounts.name', 'LIKE', '%بهای%')
                    ->orWhere('accounts.name', 'LIKE', '%کالا%');
            })
            ->selectRaw('YEAR(financial_ledgers.created_at) as y')
            ->selectRaw('MONTH(financial_ledgers.created_at) as m')
            ->selectRaw('SUM(CASE WHEN financial_ledgers.amount_base > 0 THEN financial_ledgers.amount_base ELSE 0 END) as total')
            ->groupBy('y', 'm')
            ->orderBy('y')
            ->orderBy('m')
            ->get();

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                sprintf('%04d-%02d', (int) $row->y, (int) $row->m),
                (float) $row->total,
            ];
        }

        return [
            'title' => 'روند ماهانه بهای تمام‌شده',
            'period' => $dateRange,
            'columns' => ['ماه', 'COGS'],
            'rows' => $tableRows,
            'monthly_cogs' => $rows,
        ];
    }

    // ==========================================
    // گزارش‌های فروش (Sales)
    // ==========================================

    public function getSalesSummary(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = CustomerInvoice::whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])->get();
        
        return [
            'title' => 'خلاصه فروش',
            'period' => $dateRange,
            'total_sales' => $invoices->sum('total_amount'),
            'invoice_count' => $invoices->count(),
            'average_invoice' => $invoices->count() > 0 ? $invoices->sum('total_amount') / $invoices->count() : 0,
        ];
    }

    public function getSalesByCustomer(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $invoices = CustomerInvoice::with('customer')
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->groupBy('customer_id');
        
        $data = [];
        
        foreach ($invoices as $customerId => $customerInvoices) {
            $data[] = [
                'customer' => $customerInvoices->first()->customer->name,
                'invoice_count' => $customerInvoices->count(),
                'total_amount' => $customerInvoices->sum('total_amount'),
            ];
        }
        
        usort($data, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);
        
        return [
            'title' => 'فروش به تفکیک مشتری',
            'period' => $dateRange,
            'customers' => $data,
        ];
    }

    public function getSalesByProduct(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.sales_by_product');
    }

    public function getSalesTrend(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $sales = CustomerInvoice::whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return [
            'title' => 'روند فروش',
            'period' => $dateRange,
            'columns' => ['تاریخ', 'فروش'],
            'rows' => $sales->map(static fn ($row) => [
                (string) $row->date,
                (float) ($row->total ?? 0),
            ])->all(),
            'daily_sales' => $sales,
        ];
    }

    // ==========================================
    // گزارش‌های تطبیق (Reconciliation)
    // ==========================================

    public function getBankReconciliation(array $filters = []): array
    {
        return [
            'title' => trans('accounting::accounting.reports.bank_reconciliation.title'),
            'message' => trans('accounting::accounting.reports.bank_reconciliation.placeholder'),
            'hint' => trans('accounting::accounting.reports.bank_reconciliation.hint'),
            'layout' => 'placeholder',
        ];
    }

    public function getCashboxReconciliation(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.cashbox_reconciliation');
    }

    public function getUnreconciledItems(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.unreconciled_items');
    }

    public function getReconciliationHistory(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.reconciliation_history');
    }

    // ==========================================
    // گزارش‌های تحلیلی (Analytics)
    // ==========================================

    public function getCashFlowForecast(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.cash_flow_forecast');
    }

    public function getFinancialRatios(array $filters = []): array
    {
        if (empty($filters['as_of_date'])) {
            $period = $this->getDateRange($filters);
            $filters['as_of_date'] = Carbon::parse((string) $period['end'])->format('Y-m-d');
        }

        $balanceSheet = $this->getBalanceSheet($filters);
        $incomeStatement = $this->getIncomeStatement($filters);
        
        $assets = $balanceSheet['assets']['total'];
        $liabilities = $balanceSheet['liabilities']['total'];
        $equity = $balanceSheet['equity']['total'];
        $netIncome = $incomeStatement['net_income'];
        $currentAssetPrefixes = $this->getCodePrefixesFromSetting('accounting.ratios.current_asset_prefixes', ['11']);
        $currentLiabilityPrefixes = $this->getCodePrefixesFromSetting('accounting.ratios.current_liability_prefixes', ['21']);
        $currentAssets = $this->sumBalanceByCodePrefixes($currentAssetPrefixes, (string) ($balanceSheet['as_of_datetime'] ?? $balanceSheet['as_of_date']));
        $currentLiabilities = $this->sumBalanceByCodePrefixes($currentLiabilityPrefixes, (string) ($balanceSheet['as_of_datetime'] ?? $balanceSheet['as_of_date']));

        $currentRatio = $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0.0;
        $grossMargin = (float) ($incomeStatement['gross_margin'] ?? 0);
        $netMargin = (float) ($incomeStatement['net_margin'] ?? 0);
        $roa = $assets > 0 ? ($netIncome / $assets) * 100 : 0.0;
        $roe = $equity > 0 ? ($netIncome / $equity) * 100 : 0.0;
        $debtToEquity = $equity > 0 ? $liabilities / $equity : 0.0;
        $debtToAssets = $assets > 0 ? $liabilities / $assets : 0.0;
        
        return [
            'title' => 'نسبت‌های مالی',
            'columns' => ['شاخص', 'مقدار'],
            'rows' => [
                ['Current Ratio', $currentRatio],
                ['Gross Margin %', $grossMargin],
                ['Net Margin %', $netMargin],
                ['ROA %', $roa],
                ['ROE %', $roe],
                ['Debt/Equity', $debtToEquity],
                ['Debt/Assets', $debtToAssets],
            ],
            'liquidity' => [
                'current_assets' => $currentAssets,
                'current_liabilities' => $currentLiabilities,
                'current_ratio' => $currentRatio,
            ],
            'profitability' => [
                'gross_margin' => $grossMargin,
                'net_margin' => $netMargin,
            ],
            'efficiency' => [
                'roa' => $roa,
                'roe' => $roe,
            ],
            'leverage' => [
                'debt_to_equity' => $debtToEquity,
                'debt_to_assets' => $debtToAssets,
            ],
        ];
    }

    public function getProfitabilityAnalysis(array $filters = []): array
    {
        $incomeStatement = $this->getIncomeStatement($filters);
        
        return [
            'title' => 'تحلیل سودآوری',
            'gross_profit' => $incomeStatement['gross_profit'],
            'gross_margin' => $incomeStatement['gross_margin'],
            'net_income' => $incomeStatement['net_income'],
            'net_margin' => $incomeStatement['net_margin'],
        ];
    }

    public function getRevenueTrend(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $revenue = CustomerInvoice::whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('YEAR(invoice_date) as year, MONTH(invoice_date) as month, SUM(total_amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        return [
            'title' => 'روند درآمد',
            'period' => $dateRange,
            'columns' => ['ماه', 'درآمد'],
            'rows' => $revenue->map(static fn ($row) => [
                sprintf('%04d-%02d', (int) $row->year, (int) $row->month),
                (float) ($row->total ?? 0),
            ])->all(),
            'monthly_revenue' => $revenue,
        ];
    }

    /**
     * @param list<string> $prefixes
     */
    protected function sumBalanceByCodePrefixes(array $prefixes, string $asOfDate): float
    {
        $cleanPrefixes = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $prefixes), static fn ($v) => $v !== ''));
        if ($cleanPrefixes === []) {
            return 0.0;
        }

        $accounts = Account::query()
            ->where(function ($q) use ($cleanPrefixes) {
                foreach ($cleanPrefixes as $prefix) {
                    $q->orWhere('code', 'LIKE', $prefix . '%');
                }
            })
            ->where('active', true)
            ->get(['id', 'account_type']);
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $sums = FinancialLedger::query()
            ->whereIn('account_id', $accounts->pluck('id')->all())
            ->where('created_at', '<=', $asOfDate)
            ->selectRaw('account_id, SUM(CASE WHEN amount_base > 0 THEN amount_base ELSE 0 END) as debit_sum')
            ->selectRaw('SUM(CASE WHEN amount_base < 0 THEN ABS(amount_base) ELSE 0 END) as credit_sum')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $total = 0.0;
        foreach ($accounts as $account) {
            $sum = $sums->get($account->id);
            $debit = (float) ($sum->debit_sum ?? 0.0);
            $credit = (float) ($sum->credit_sum ?? 0.0);
            $isCreditNormal = in_array((string) $account->account_type, [Account::TYPE_LIABILITY, Account::TYPE_EQUITY, Account::TYPE_INCOME], true);
            $total += $isCreditNormal ? ($credit - $debit) : ($debit - $credit);
        }

        return $total;
    }

    public function getPeriodComparison(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $currentStart = Carbon::parse((string) $dateRange['start'])->startOfMonth();
        $currentEnd = Carbon::parse((string) $dateRange['end'])->endOfMonth();
        $previousStart = $currentStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();

        $current = $this->getVATReport([
            'from_date' => $currentStart->format('Y-m-d'),
            'to_date' => $currentEnd->format('Y-m-d'),
        ]);
        $previous = $this->getVATReport([
            'from_date' => $previousStart->format('Y-m-d'),
            'to_date' => $previousEnd->format('Y-m-d'),
        ]);

        $metricRows = [
            [
                'خالص دوره (Accrual)',
                (float) ($current['vat_payable'] ?? 0),
                (float) ($previous['vat_payable'] ?? 0),
                (float) ($current['vat_payable'] ?? 0) - (float) ($previous['vat_payable'] ?? 0),
            ],
            [
                'پرداخت VAT',
                (float) ($current['remitted_vat'] ?? 0),
                (float) ($previous['remitted_vat'] ?? 0),
                (float) ($current['remitted_vat'] ?? 0) - (float) ($previous['remitted_vat'] ?? 0),
            ],
            [
                'مانده قابل پرداخت',
                (float) ($current['net_payable_remaining'] ?? 0),
                (float) ($previous['net_payable_remaining'] ?? 0),
                (float) ($current['net_payable_remaining'] ?? 0) - (float) ($previous['net_payable_remaining'] ?? 0),
            ],
        ];

        return [
            'title' => 'مقایسه ماه جاری با ماه قبل (VAT)',
            'period' => [
                'start' => $previousStart->format('Y-m-d'),
                'end' => $currentEnd->format('Y-m-d'),
            ],
            'columns' => ['شاخص', 'ماه جاری', 'ماه قبل', 'اختلاف'],
            'rows' => $metricRows,
            'summary' => [
                'current_month' => $currentStart->format('Y-m'),
                'previous_month' => $previousStart->format('Y-m'),
            ],
        ];
    }

    // ==========================================
    // گزارش‌های سال مالی
    // ==========================================

    public function getFiscalYearPerformance(array $filters = []): array
    {
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        
        if (!$fiscalYear) {
            return ['title' => 'عملکرد سال مالی', 'error' => 'سال مالی فعال یافت نشد'];
        }
        
        $filters['start_date'] = $fiscalYear->start_date;
        $filters['end_date'] = $fiscalYear->end_date;
        
        $incomeStatement = $this->getIncomeStatement($filters);
        
        return [
            'title' => 'عملکرد سال مالی - ' . $fiscalYear->year_code,
            'fiscal_year' => $fiscalYear,
            'revenue' => $incomeStatement['revenue']['total'],
            'expenses' => $incomeStatement['operating_expenses']['total'] + $incomeStatement['cost_of_goods_sold'],
            'net_income' => $incomeStatement['net_income'],
        ];
    }

    public function getYearOverYear(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.year_over_year');
    }

    public function getClosingReport(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.closing_report');
    }

    // ==========================================
    // گزارش‌های Audit
    // ==========================================

    public function getAuditTrail(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.audit_trail');
    }

    public function getDocumentReversals(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $reversals = AccountingDocument::where('is_reversal', true)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();
        
        return [
            'title' => 'سندهای اصلاحی',
            'period' => $dateRange,
            'reversals' => $reversals,
            'count' => $reversals->count(),
        ];
    }

    public function getAccountingActivityLog(array $filters = []): array
    {
        return $this->stubReportResponse('accounting::accounting.reports.placeholder.titles.accounting_activity_log');
    }

    public function getDiscrepancies(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $tolerance = (float) ($filters['tolerance'] ?? 0.01);

        $documents = AccountingDocument::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get(['id', 'document_type', 'document_number', 'document_date']);

        $documentIds = $documents->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($documentIds === []) {
            return [
                'title' => 'گزارش مغایرت‌ها',
                'period' => $dateRange,
                'columns' => ['نوع مغایرت', 'نوع سند', 'شماره سند', 'تاریخ سند', 'جمع بدهکار', 'جمع بستانکار', 'اختلاف'],
                'rows' => [],
                'summary' => ['count' => 0, 'unbalanced' => 0, 'missing_ledger' => 0, 'orphan_ledger' => 0],
            ];
        }

        $ledgerAgg = FinancialLedger::query()
            ->whereIn('accounting_document_id', $documentIds)
            ->select('accounting_document_id')
            ->selectRaw('SUM(COALESCE(debit_amount,0)) AS total_debit')
            ->selectRaw('SUM(COALESCE(credit_amount,0)) AS total_credit')
            ->groupBy('accounting_document_id')
            ->get()
            ->keyBy('accounting_document_id');

        $rows = [];
        $unbalancedCount = 0;
        $missingLedgerCount = 0;
        foreach ($documents as $document) {
            $agg = $ledgerAgg->get((int) $document->id);
            if ($agg === null) {
                $missingLedgerCount++;
                $rows[] = [
                    'بدون ردیف دفترکل',
                    (string) ($document->document_type ?? ''),
                    (string) ($document->document_number ?? ('DOC-'.$document->id)),
                    optional($document->document_date)->format('Y-m-d'),
                    0,
                    0,
                    0,
                ];
                continue;
            }

            $debit = round((float) ($agg->total_debit ?? 0), 4);
            $credit = round((float) ($agg->total_credit ?? 0), 4);
            $delta = round($debit - $credit, 4);
            if (abs($delta) > $tolerance) {
                $unbalancedCount++;
                $rows[] = [
                    'سند نامتوازن',
                    (string) ($document->document_type ?? ''),
                    (string) ($document->document_number ?? ('DOC-'.$document->id)),
                    optional($document->document_date)->format('Y-m-d'),
                    $debit,
                    $credit,
                    $delta,
                ];
            }
        }

        $orphanLedgers = FinancialLedger::query()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where(function ($query) use ($documentIds): void {
                $query->whereNull('accounting_document_id')
                    ->orWhereNotIn('accounting_document_id', $documentIds);
            })
            ->count();
        if ($orphanLedgers > 0) {
            $rows[] = [
                'ردیف یتیم دفترکل',
                '-',
                '-',
                '-',
                0,
                0,
                0,
            ];
        }

        return [
            'title' => 'گزارش مغایرت‌ها',
            'period' => $dateRange,
            'columns' => ['نوع مغایرت', 'نوع سند', 'شماره سند', 'تاریخ سند', 'جمع بدهکار', 'جمع بستانکار', 'اختلاف'],
            'rows' => $rows,
            'summary' => [
                'count' => count($rows),
                'unbalanced' => $unbalancedCount,
                'missing_ledger' => $missingLedgerCount,
                'orphan_ledger' => (int) $orphanLedgers,
            ],
            'tolerance' => $tolerance,
        ];
    }

    public function getEmployeeLoanBalances(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $employeeId = (int) ($filters['employee_id'] ?? 0);

        $query = EmployeeLoan::query()->with('employee');
        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $rows = [];
        foreach ($query->orderByDesc('id')->get() as $loan) {
            $rows[] = [
                'loan_number' => $loan->loan_number,
                'employee' => $loan->employee?->name,
                'principal_amount' => (float) $loan->principal_amount,
                'total_interest_amount' => (float) $loan->total_interest_amount,
                'remaining_total' => (float) $loan->remaining_total,
                'status' => (string) $loan->status,
            ];
        }

        return [
            'title' => 'گزارش مانده وام کارکنان',
            'period' => $dateRange,
            'columns' => ['شماره وام', 'کارمند', 'اصل وام', 'جمع بهره', 'مانده کل', 'وضعیت'],
            'rows' => array_map(static fn (array $row): array => [
                $row['loan_number'],
                $row['employee'],
                $row['principal_amount'],
                $row['total_interest_amount'],
                $row['remaining_total'],
                $row['status'],
            ], $rows),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'filter_employee' => true,
            'filter_status_options' => [
                'draft' => 'پیش‌نویس',
                'active' => 'فعال',
                'closed' => 'تسویه‌شده',
                'cancelled' => 'لغوشده',
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'status' => (string) ($filters['status'] ?? ''),
            ],
        ];
    }

    public function getEmployeeLoanInstallmentsDue(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $employeeId = (int) ($filters['employee_id'] ?? 0);

        $query = EmployeeLoanInstallment::query()->with(['loan.employee'])
            ->whereBetween('due_date', [$dateRange['start'], $dateRange['end']]);

        if ($employeeId > 0) {
            $query->whereHas('loan', static function ($q) use ($employeeId): void {
                $q->where('employee_id', $employeeId);
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $rows = [];
        foreach ($query->orderBy('due_date')->get() as $installment) {
            $rows[] = [
                optional($installment->loan)->loan_number,
                optional(optional($installment->loan)->employee)->name,
                $installment->due_date?->format('Y-m-d'),
                (float) $installment->principal_amount,
                (float) $installment->interest_amount,
                (float) $installment->installment_amount,
                (float) $installment->remaining_amount,
                (string) $installment->status,
            ];
        }

        return [
            'title' => 'گزارش سررسید اقساط وام کارکنان',
            'period' => $dateRange,
            'columns' => ['شماره وام', 'کارمند', 'سررسید', 'اصل', 'بهره', 'مبلغ قسط', 'مانده قسط', 'وضعیت'],
            'rows' => $rows,
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'filter_employee' => true,
            'filter_status_options' => [
                'pending' => 'باز',
                'partially_paid' => 'بخشی پرداخت شده',
                'paid' => 'تسویه‌شده',
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'status' => (string) ($filters['status'] ?? ''),
            ],
        ];
    }

    public function getEmployeeContracts(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $expiringDays = max(0, (int) ($filters['expiring_days'] ?? 90));
        $expiringUntil = Carbon::parse($dateRange['end'])->addDays($expiringDays)->format('Y-m-d');

        $query = EmployeeContract::query()->with('employee');
        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $contracts = $query->orderByDesc('effective_from')->orderByDesc('id')->get();

        $rows = [];
        $activeCount = 0;
        $expiringCount = 0;
        foreach ($contracts as $contract) {
            $isActive = (string) $contract->status === EmployeeContract::STATUS_ACTIVE;
            if ($isActive) {
                $activeCount++;
            }
            $effectiveTo = $contract->effective_to?->format('Y-m-d');
            $isExpiring = $isActive && $effectiveTo !== null && $effectiveTo <= $expiringUntil;
            if ($isExpiring) {
                $expiringCount++;
            }

            $rows[] = [
                $contract->contract_number,
                $contract->employee?->name,
                $contract->effective_from?->format('Y-m-d'),
                $effectiveTo ?: '∞',
                (float) $contract->base_salary,
                (string) $contract->status,
                $isExpiring ? 'yes' : 'no',
            ];
        }

        return [
            'title' => 'گزارش قراردادهای کارکنان',
            'period' => $dateRange,
            'columns' => ['شماره قرارداد', 'کارمند', 'شروع', 'پایان', 'حقوق پایه', 'وضعیت', 'رو به اتمام'],
            'rows' => $rows,
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'filter_employee' => true,
            'filter_status_options' => [
                EmployeeContract::STATUS_DRAFT => 'پیش‌نویس',
                EmployeeContract::STATUS_ACTIVE => 'فعال',
                EmployeeContract::STATUS_ENDED => 'خاتمه‌یافته',
                EmployeeContract::STATUS_CANCELLED => 'لغوشده',
            ],
            'summary' => [
                'active_contracts' => $activeCount,
                'expiring_contracts' => $expiringCount,
                'all_contracts' => count($rows),
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'status' => $status,
                'expiring_days' => $expiringDays,
            ],
        ];
    }

    public function getAttendanceMonthlySummary(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $period = AttendancePeriod::query()
            ->whereDate('period_start', '<=', $dateRange['start'])
            ->whereDate('period_end', '>=', $dateRange['end'])
            ->orderByDesc('id')
            ->first()
            ?? AttendancePeriod::query()->orderByDesc('id')->first();
        $rows = $period instanceof AttendancePeriod
            ? app(AttendanceWorklogService::class)->periodSummaryRows($period)
            : [];

        return [
            'title' => 'گزارش خلاصه کارکرد ماهانه',
            'period' => $dateRange,
            'columns' => ['کارمند', 'روز برنامه', 'روز کارکرد', 'روز قابل پرداخت', 'اضافه‌کار (ساعت)', 'غیبت (روز)'],
            'rows' => array_map(static fn (array $row): array => [
                (string) ($row['employee_name'] ?? ''),
                (float) ($row['planned_days'] ?? 0),
                (float) ($row['worked_days'] ?? 0),
                (float) ($row['payable_days'] ?? 0),
                (float) ($row['overtime_hours'] ?? 0),
                (float) ($row['absence_days'] ?? 0),
            ], $rows),
        ];
    }

    public function getAttendanceOvertimeDetail(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $rows = AttendanceDaily::query()
            ->with('employee')
            ->whereBetween('work_date', [$dateRange['start'], $dateRange['end']])
            ->where('overtime_minutes', '>', 0)
            ->orderBy('work_date')
            ->get()
            ->map(static fn (AttendanceDaily $row): array => [
                (string) ($row->employee?->name ?? ('#'.$row->employee_id)),
                (string) $row->work_date?->format('Y-m-d'),
                round(((float) $row->overtime_minutes / 60), 2),
                (int) $row->worked_minutes,
                (string) $row->status,
            ])
            ->all();

        return [
            'title' => 'گزارش جزئیات اضافه‌کار',
            'period' => $dateRange,
            'columns' => ['کارمند', 'تاریخ', 'اضافه‌کار (ساعت)', 'دقایق کارکرد', 'وضعیت'],
            'rows' => $rows,
        ];
    }

    public function getAttendanceLeaveAbsence(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $rows = AttendanceDaily::query()
            ->with('employee')
            ->whereBetween('work_date', [$dateRange['start'], $dateRange['end']])
            ->where(static function ($query): void {
                $query->where('leave_minutes', '>', 0)
                    ->orWhere('absence_minutes', '>', 0);
            })
            ->orderBy('work_date')
            ->get()
            ->map(static fn (AttendanceDaily $row): array => [
                (string) ($row->employee?->name ?? ('#'.$row->employee_id)),
                (string) $row->work_date?->format('Y-m-d'),
                round(((float) $row->leave_minutes / 60), 2),
                round(((float) $row->absence_minutes / 60), 2),
                (float) $row->payable_day_fraction,
            ])
            ->all();

        return [
            'title' => 'گزارش مرخصی و غیبت',
            'period' => $dateRange,
            'columns' => ['کارمند', 'تاریخ', 'مرخصی (ساعت)', 'غیبت (ساعت)', 'روز قابل پرداخت'],
            'rows' => $rows,
        ];
    }

    public function getAttendanceTerminationSettlement(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $rows = AttendanceDaily::query()
            ->with('employee')
            ->whereBetween('work_date', [$dateRange['start'], $dateRange['end']])
            ->where('is_termination_final_day', true)
            ->orderBy('work_date')
            ->get()
            ->map(static fn (AttendanceDaily $row): array => [
                (string) ($row->employee?->name ?? ('#'.$row->employee_id)),
                (string) $row->work_date?->format('Y-m-d'),
                (float) $row->worked_day_fraction,
                (float) $row->payable_day_fraction,
                (string) $row->notes,
            ])
            ->all();

        return [
            'title' => 'گزارش تسویه پایان همکاری',
            'period' => $dateRange,
            'columns' => ['کارمند', 'آخرین روز', 'روز کارکرد', 'روز قابل پرداخت', 'توضیحات'],
            'rows' => $rows,
        ];
    }

    public function getAttendancePayrollReconciliation(array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        $rows = app(AttendanceWorklogService::class)->reconciliationRows($dateRange['start'], $dateRange['end']);

        return [
            'title' => 'گزارش تطبیق کارکرد ← حقوق ← دفتر کل',
            'period' => $dateRange,
            'columns' => ['شماره فیش', 'کارمند', 'روز قابل پرداخت', 'ضریب تسهیم', 'حقوق پایه', 'مزایا', 'خالص', 'سند تعهد'],
            'rows' => array_map(static fn (array $row): array => [
                (string) ($row['run_number'] ?? ''),
                (string) ($row['employee_name'] ?? ''),
                (float) ($row['payable_days'] ?? 0),
                (float) ($row['proration_factor'] ?? 1),
                (float) ($row['base_salary'] ?? 0),
                (float) ($row['benefits'] ?? 0),
                (float) ($row['net_salary'] ?? 0),
                (int) ($row['accrual_journal_id'] ?? 0),
            ], $rows),
        ];
    }

    public function getInsuranceMonthlyReport(array $filters = []): array
    {
        if (
            empty($filters['from_date'])
            && empty($filters['to_date'])
            && empty($filters['start_date'])
            && empty($filters['end_date'])
        ) {
            $filters['from_date'] = Carbon::now()->startOfMonth()->format('Y-m-d');
            $filters['to_date'] = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $dateRange = $this->getDateRange($filters);
        $start = Carbon::parse((string) $dateRange['start'])->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse((string) $dateRange['end'])->endOfDay()->format('Y-m-d H:i:s');

        $accountMap = $this->resolveInsurancePayableAccounts();
        if ($accountMap['all_ids'] === []) {
            return [
                'title' => trans('accounting::accounting.reports.insurance_monthly.title'),
                'period' => $dateRange,
                'error' => trans('accounting::accounting.reports.insurance_monthly.errors.accounts_not_configured'),
            ];
        }

        $openingBalance = $this->insuranceBalanceAsOf($accountMap['all_ids'], Carbon::parse($start)->subSecond()->format('Y-m-d H:i:s'));
        $closingBalance = $this->insuranceBalanceAsOf($accountMap['all_ids'], $end);

        $journalRows = $this->insuranceMonthlySourceRows($accountMap, $start, $end);
        usort($journalRows, static function (array $a, array $b): int {
            $c = strcmp((string) ($a['posted_at'] ?? ''), (string) ($b['posted_at'] ?? ''));
            if ($c !== 0) {
                return $c;
            }

            return strcmp((string) ($a['document_number'] ?? ''), (string) ($b['document_number'] ?? ''));
        });

        $accrualEmployee = 0.0;
        $accrualEmployer = 0.0;
        $paymentTotal = 0.0;
        foreach ($journalRows as $row) {
            $accrualEmployee += (float) ($row['accrual_employee'] ?? 0);
            $accrualEmployer += (float) ($row['accrual_employer'] ?? 0);
            $paymentTotal += (float) ($row['payment_total'] ?? 0);
        }
        $accrualTotal = $accrualEmployee + $accrualEmployer;

        $ledgerMovement = $this->insuranceLedgerMovementTotals($accountMap['all_ids'], $start, $end);
        $formulaClosingBalance = $openingBalance + $accrualTotal - $paymentTotal;
        $formulaDifference = $closingBalance - $formulaClosingBalance;
        $ledgerDifference = ($ledgerMovement['accrual_total'] - $ledgerMovement['payment_total']) - ($closingBalance - $openingBalance);
        $isBalanced = abs($formulaDifference) < 0.01 && abs($ledgerDifference) < 0.01;

        $dto = new InsuranceMonthlyReportDto(
            periodStart: Carbon::parse($start)->format('Y-m-d H:i:s'),
            periodEnd: Carbon::parse($end)->format('Y-m-d H:i:s'),
            generatedAt: now()->format('Y-m-d H:i:s'),
            generatedByUserId: \RMS\Accounting\Support\AuditActor::actorId(),
            openingBalance: $openingBalance,
            accrualEmployee: $accrualEmployee,
            accrualEmployer: $accrualEmployer,
            paymentTotal: $paymentTotal,
            ledgerAccrualTotal: $ledgerMovement['accrual_total'],
            ledgerPaymentTotal: $ledgerMovement['payment_total'],
            closingBalance: $closingBalance,
            formulaClosingBalance: $formulaClosingBalance,
            formulaDifference: $formulaDifference,
            ledgerDifference: $ledgerDifference,
            isBalanced: $isBalanced,
            sourceRows: $journalRows
        );

        $payload = $dto->toArray();
        $payload['title'] = trans('accounting::accounting.reports.insurance_monthly.title');
        $payload['filters'] = [
            'from_date' => Carbon::parse($start)->format('Y-m-d'),
            'to_date' => Carbon::parse($end)->format('Y-m-d'),
        ];

        return $payload;
    }

    /**
     * @param array<int, array<string,mixed>> $sourceRows
     * @return array<int, array<int,mixed>>
     */
    public function insuranceMonthlyExcelRows(array $sourceRows): array
    {
        $rows = [];
        foreach ($sourceRows as $row) {
            $rows[] = [
                (string) ($row['posted_at'] ?? ''),
                (string) ($row['source_label'] ?? ''),
                (string) ($row['reference'] ?? ''),
                (string) ($row['document_number'] ?? ''),
                (string) ($row['description'] ?? ''),
                (float) ($row['accrual_employee'] ?? 0),
                (float) ($row['accrual_employer'] ?? 0),
                (float) ($row['accrual_total'] ?? 0),
                (float) ($row['payment_total'] ?? 0),
                (float) ($row['net_change'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *   employee_id:int|null,
     *   employer_id:int|null,
     *   legacy_id:int|null,
     *   all_ids:array<int,int>,
     *   category_by_account_id:array<int,string>
     * }
     */
    protected function resolveInsurancePayableAccounts(): array
    {
        $employeeCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.employee_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.employee_insurance_payable', '2105'));
        $employerCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.employer_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.employer_insurance_payable', '2106'));
        $legacyCode = (string) (\RMS\Core\Models\Setting::get('accounting.system_accounts.liabilities.social_insurance_payable')
            ?: config('accounting.system_accounts.liabilities.social_insurance_payable', '2104'));

        $accounts = Account::query()
            ->whereIn('code', array_values(array_unique(array_filter([$employeeCode, $employerCode, $legacyCode]))))
            ->get();

        $categoryByAccountId = [];
        $employeeId = null;
        $employerId = null;
        $legacyId = null;
        foreach ($accounts as $account) {
            if ((string) $account->code === $employeeCode) {
                $employeeId = (int) $account->id;
                $categoryByAccountId[(int) $account->id] = 'employee';
                continue;
            }
            if ((string) $account->code === $employerCode) {
                $employerId = (int) $account->id;
                $categoryByAccountId[(int) $account->id] = 'employer';
                continue;
            }
            if ((string) $account->code === $legacyCode) {
                $legacyId = (int) $account->id;
                $categoryByAccountId[(int) $account->id] = 'legacy';
            }
        }

        $allIds = array_values(array_unique(array_filter([$employeeId, $employerId, $legacyId])));

        return [
            'employee_id' => $employeeId,
            'employer_id' => $employerId,
            'legacy_id' => $legacyId,
            'all_ids' => $allIds,
            'category_by_account_id' => $categoryByAccountId,
        ];
    }

    /**
     * @param array<int,int> $accountIds
     */
    protected function insuranceBalanceAsOf(array $accountIds, string $asOf): float
    {
        if ($accountIds === []) {
            return 0.0;
        }

        $docTime = "COALESCE(ad.posted_at, ad.created_at)";
        $row = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereIn('fl.account_id', $accountIds)
            ->whereRaw($docTime.' <= ?', [$asOf])
            ->selectRaw('COALESCE(SUM(fl.debit_amount),0) as debit_sum')
            ->selectRaw('COALESCE(SUM(fl.credit_amount),0) as credit_sum')
            ->first();

        $debit = (float) ($row->debit_sum ?? 0);
        $credit = (float) ($row->credit_sum ?? 0);

        return $credit - $debit;
    }

    /**
     * @param array<int,int> $accountIds
     * @return array{accrual_total:float,payment_total:float}
     */
    protected function insuranceLedgerMovementTotals(array $accountIds, string $start, string $end): array
    {
        if ($accountIds === []) {
            return ['accrual_total' => 0.0, 'payment_total' => 0.0];
        }

        $docTime = "COALESCE(ad.posted_at, ad.created_at)";
        $row = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereIn('fl.account_id', $accountIds)
            ->whereRaw($docTime.' between ? and ?', [$start, $end])
            ->selectRaw('COALESCE(SUM(fl.credit_amount),0) as accrual_total')
            ->selectRaw('COALESCE(SUM(fl.debit_amount),0) as payment_total')
            ->first();

        return [
            'accrual_total' => (float) ($row->accrual_total ?? 0),
            'payment_total' => (float) ($row->payment_total ?? 0),
        ];
    }

    /**
     * @param array{
     *   all_ids:array<int,int>,
     *   category_by_account_id:array<int,string>
     * } $accountMap
     * @return array<int, array<string,mixed>>
     */
    protected function insuranceMonthlySourceRows(array $accountMap, string $start, string $end): array
    {
        if (($accountMap['all_ids'] ?? []) === []) {
            return [];
        }

        $docTime = "COALESCE(ad.posted_at, ad.created_at)";
        $journalRows = DB::table('manual_journals as mj')
            ->join('manual_journal_lines as mjl', 'mjl.manual_journal_id', '=', 'mj.id')
            ->join('accounting_documents as ad', function ($join): void {
                $join->on('ad.reference_id', '=', 'mj.id')
                    ->where('ad.reference_type', '=', 'manual_journal');
            })
            ->leftJoin('payroll_runs as pr_acc', 'pr_acc.accrual_manual_journal_id', '=', 'mj.id')
            ->leftJoin('payroll_runs as pr_pay', 'pr_pay.insurance_remittance_manual_journal_id', '=', 'mj.id')
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereIn('mjl.account_id', (array) $accountMap['all_ids'])
            ->whereRaw($docTime.' between ? and ?', [$start, $end])
            ->groupBy(
                'mj.id',
                'mj.journal_number',
                'mj.description',
                'ad.document_number',
                'pr_acc.id',
                'pr_acc.run_number',
                'pr_pay.id',
                'pr_pay.run_number'
            )
            ->orderByRaw('MIN('.$docTime.') asc')
            ->selectRaw('mj.id as journal_id')
            ->selectRaw('mj.journal_number as journal_number')
            ->selectRaw('MAX(mj.description) as journal_description')
            ->selectRaw('MAX(ad.document_number) as document_number')
            ->selectRaw('MIN('.$docTime.') as posted_at')
            ->selectRaw('MAX(pr_acc.id) as payroll_accrual_run_id')
            ->selectRaw('MAX(pr_acc.run_number) as payroll_accrual_run_number')
            ->selectRaw('MAX(pr_pay.id) as payroll_payment_run_id')
            ->selectRaw('MAX(pr_pay.run_number) as payroll_payment_run_number')
            ->selectRaw('COALESCE(SUM(mjl.debit_amount),0) as debit_sum')
            ->selectRaw('COALESCE(SUM(mjl.credit_amount),0) as credit_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN mjl.account_id = '.((int) ($accountMap['employee_id'] ?? 0)).' THEN mjl.credit_amount ELSE 0 END),0) as emp_credit_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN mjl.account_id = '.((int) ($accountMap['employer_id'] ?? 0)).' THEN mjl.credit_amount ELSE 0 END),0) as empr_credit_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN mjl.account_id = '.((int) ($accountMap['legacy_id'] ?? 0)).' THEN mjl.credit_amount ELSE 0 END),0) as legacy_credit_sum')
            ->get();

        $rows = [];
        foreach ($journalRows as $row) {
            $debit = (float) $row->debit_sum;
            $credit = (float) $row->credit_sum;
            $accrualEmployee = (float) $row->emp_credit_sum;
            $accrualEmployer = (float) $row->empr_credit_sum + (float) $row->legacy_credit_sum;
            $accrualTotal = $accrualEmployee + $accrualEmployer;
            $paymentTotal = $debit;

            $isPayrollRun = ((int) $row->payroll_accrual_run_id > 0) || ((int) $row->payroll_payment_run_id > 0);
            if ($isPayrollRun) {
                $sourceType = 'payroll_run';
                $sourceLabel = trans('accounting::accounting.reports.insurance_monthly.sources.payroll_run');
                $reference = (string) ($row->payroll_accrual_run_number ?: $row->payroll_payment_run_number ?: $row->journal_number);
            } elseif ($paymentTotal > 0 && $accrualTotal <= 0) {
                $sourceType = 'insurance_payment_settlement';
                $sourceLabel = trans('accounting::accounting.reports.insurance_monthly.sources.insurance_payment_settlement');
                $reference = (string) $row->journal_number;
            } else {
                $sourceType = 'manual_insurance_journal';
                $sourceLabel = trans('accounting::accounting.reports.insurance_monthly.sources.manual_insurance_journal');
                $reference = (string) $row->journal_number;
            }

            $rows[] = [
                'posted_at' => (string) Carbon::parse((string) $row->posted_at)->format('Y-m-d H:i:s'),
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'reference' => $reference,
                'document_number' => (string) ($row->document_number ?? ''),
                'description' => (string) ($row->journal_description ?? ''),
                'accrual_employee' => $accrualEmployee,
                'accrual_employer' => $accrualEmployer,
                'accrual_total' => $accrualTotal,
                'payment_total' => $paymentTotal,
                'net_change' => $accrualTotal - $paymentTotal,
            ];
        }

        return $rows;
    }
}
