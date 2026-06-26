<?php

namespace RMS\Accounting\Http\Controllers\Admin\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseCategory;
use RMS\Accounting\Models\POSTerminal;
use RMS\Accounting\Services\AccountingDateInputNormalizer;

trait ManagesCustomExpenseForm
{
    use ParsesAccountingMoneyInput;

    /**
     * @return array<int, int>
     */
    protected function expenseFeaturedCategoryIds(): array
    {
        $legacy = config('expense_ui.featured_category_ids');
        $ids = $legacy !== null ? $legacy : config('accounting.expense_ui.featured_category_ids', []);

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }

    protected function expenseUsageLookbackDays(): int
    {
        $legacy = config('expense_ui.usage_lookback_days');
        if ($legacy !== null) {
            return max(1, (int) $legacy);
        }

        return max(1, (int) config('accounting.expense_ui.usage_lookback_days', 90));
    }

    protected function expenseUsageTopN(): int
    {
        $legacy = config('expense_ui.usage_top_n');
        if ($legacy !== null) {
            return max(0, (int) $legacy);
        }

        return max(0, (int) config('accounting.expense_ui.usage_top_n', 15));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    protected function buildCategoryGroups(): array
    {
        $featuredIds = $this->expenseFeaturedCategoryIds();
        $lookback = $this->expenseUsageLookbackDays();
        $topN = $this->expenseUsageTopN();

        $usageIds = [];
        if ($topN > 0 && Schema::hasTable('expenses')) {
            $usageIds = DB::table('expenses')
                ->select('expense_category_id', DB::raw('COUNT(*) as c'))
                ->where('expense_date', '>=', now()->subDays($lookback)->toDateString())
                ->when(
                    Schema::hasColumn('expenses', 'deleted_at'),
                    fn ($q) => $q->whereNull('deleted_at')
                )
                ->groupBy('expense_category_id')
                ->orderByDesc('c')
                ->limit($topN)
                ->pluck('expense_category_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $priorityIds = [];
        foreach (array_merge($featuredIds, $usageIds) as $id) {
            if ($id > 0 && ! in_array($id, $priorityIds, true)) {
                $priorityIds[] = $id;
            }
        }

        $suggested = ExpenseCategory::query()
            ->active()
            ->whereIn('id', $priorityIds)
            ->get()
            ->sortBy(fn ($c) => array_search((int) $c->id, $priorityIds, true))
            ->values();

        $other = ExpenseCategory::query()
            ->active()
            ->when(count($priorityIds) > 0, fn ($q) => $q->whereNotIn('id', $priorityIds))
            ->orderBy('name')
            ->get();

        return [$suggested, $other];
    }

    protected function generateUniqueExpenseNumber(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $candidate = 'EXP-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            if (! Expense::where('expense_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'EXP-' . now()->format('YmdHis') . '-' . substr(sha1((string) microtime(true)), 0, 6);
    }

    /**
     * @return array<string, string>
     */
    protected function expenseTypeOptions(): array
    {
        return [
            Expense::TYPE_OPERATIONAL => trans('accounting::accounting.expense_create.types.operational'),
            Expense::TYPE_SALARY => trans('accounting::accounting.expense_create.types.salary'),
            Expense::TYPE_RENT => trans('accounting::accounting.expense_create.types.rent'),
            Expense::TYPE_UTILITIES => trans('accounting::accounting.expense_create.types.utilities'),
            Expense::TYPE_MARKETING => trans('accounting::accounting.expense_create.types.marketing'),
            Expense::TYPE_TRANSPORTATION => trans('accounting::accounting.expense_create.types.transportation'),
            Expense::TYPE_SUPPLIES => trans('accounting::accounting.expense_create.types.supplies'),
            Expense::TYPE_MAINTENANCE => trans('accounting::accounting.expense_create.types.maintenance'),
            Expense::TYPE_OTHER => trans('accounting::accounting.expense_create.types.other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function payeeTypeOptions(): array
    {
        return [
            'employee' => trans('accounting::accounting.expense_create.payee_types.employee'),
            'supplier' => trans('accounting::accounting.expense_create.payee_types.supplier'),
            'service_provider' => trans('accounting::accounting.expense_create.payee_types.service_provider'),
            'government' => trans('accounting::accounting.expense_create.payee_types.government'),
            'other' => trans('accounting::accounting.expense_create.payee_types.other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return [
            Expense::STATUS_DRAFT => trans('accounting::accounting.statuses.draft'),
            Expense::STATUS_PENDING => trans('accounting::accounting.statuses.pending'),
            Expense::STATUS_APPROVED => trans('accounting::accounting.statuses.approved'),
            Expense::STATUS_REJECTED => trans('accounting::accounting.statuses.rejected'),
            Expense::STATUS_PAID => trans('accounting::accounting.statuses.paid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expenseFormValidationRules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'string'],
            'description' => ['required', 'string', 'max:65000'],
            'status' => ['required', 'in:draft,pending,approved,rejected,paid'],
            'expense_type' => ['required', 'in:' . implode(',', array_keys($this->expenseTypeOptions()))],
            'payee_type' => ['required', 'in:' . implode(',', array_keys($this->payeeTypeOptions()))],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'payment_source_kind' => ['nullable', 'string', 'in:cash_box,bank,pos_terminal'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id', $this->paymentSourceExpenseRule()],
            'cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
        ];
    }

    /**
     * با وضعیت «پرداخت‌شده» باید دقیقاً یکی از bank_id / cash_box_id / pos_terminal_id پر باشد.
     */
    protected function paymentSourceExpenseRule(): Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $req = request();
            if (($req->input('status') ?? '') !== Expense::STATUS_PAID) {
                return;
            }
            $filled = 0;
            foreach (['bank_id', 'cash_box_id', 'pos_terminal_id'] as $key) {
                $v = $req->input($key);
                if ($v !== null && $v !== '') {
                    $filled++;
                }
            }
            if ($filled !== 1) {
                $fail(trans('accounting::accounting.expense_create.payment_source_invalid'));
            }
        };
    }

    /**
     * @return array{banks: \Illuminate\Support\Collection, cashBoxes: \Illuminate\Support\Collection, posTerminals: \Illuminate\Support\Collection}
     */
    protected function expenseTreasuryListsForForm(): array
    {
        $banks = Bank::query()->where('active', true)->orderBy('name')->get();
        $cashBoxes = CashBox::query()->where('active', true)->orderBy('name')->get();
        $posTerminals = POSTerminal::query()->active()->orderBy('name')->get();

        return [
            'banks' => $banks,
            'cashBoxes' => $cashBoxes,
            'posTerminals' => $posTerminals,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{bank_id: int|null, cash_box_id: int|null, pos_terminal_id: int|null}
     */
    protected function normalizeExpensePaymentSourceIds(array $data): array
    {
        if (($data['status'] ?? '') !== Expense::STATUS_PAID) {
            return ['bank_id' => null, 'cash_box_id' => null, 'pos_terminal_id' => null];
        }

        return [
            'bank_id' => ! empty($data['bank_id']) ? (int) $data['bank_id'] : null,
            'cash_box_id' => ! empty($data['cash_box_id']) ? (int) $data['cash_box_id'] : null,
            'pos_terminal_id' => ! empty($data['pos_terminal_id']) ? (int) $data['pos_terminal_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildExpenseAttributesFromValidated(array $data, bool $isNew): array
    {
        $currencyCode = $this->resolveDefaultCurrencyCode();
        $amount = $this->parseDecimalAmount((string) $data['amount']);
        if ($amount === null || $amount <= 0) {
            throw new \InvalidArgumentException('amount_invalid');
        }

        $amount = round($amount, $this->resolveAccountingAmountDecimalPlaces());

        $payeeName = trim((string) ($data['payee_name'] ?? ''));
        if ($payeeName === '') {
            $payeeName = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $data['description'])), 255, '') ?: '-';
        }

        $fxRate = '1';
        $amountIrr = $amount;
        if (strtoupper($currencyCode) !== 'IRR' && strtoupper($currencyCode) !== 'IRT') {
            $amountIrr = $amount;
        }

        $paymentIds = $this->normalizeExpensePaymentSourceIds($data);

        $expenseDateGregorian = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian((string) $data['expense_date']);
        if ($expenseDateGregorian === null) {
            throw new \InvalidArgumentException('expense_date_invalid');
        }

        $attrs = [
            'expense_category_id' => (int) $data['expense_category_id'],
            'expense_type' => $data['expense_type'],
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'fx_rate' => $fxRate,
            'amount_base' => $amountIrr,
            'expense_date' => $expenseDateGregorian,
            'payee_type' => $data['payee_type'],
            'payee_name' => $payeeName,
            'description' => $data['description'],
            'status' => $data['status'],
            'bank_id' => $paymentIds['bank_id'],
            'cash_box_id' => $paymentIds['cash_box_id'],
            'pos_terminal_id' => $paymentIds['pos_terminal_id'],
        ];

        if (($data['status'] ?? '') === Expense::STATUS_PAID) {
            $attrs['payment_status'] = 'paid';
            $attrs['paid_amount'] = $amount;
        } else {
            $attrs['payment_status'] = 'unpaid';
            $attrs['paid_amount'] = 0;
        }

        if ($isNew) {
            $attrs['expense_number'] = $this->generateUniqueExpenseNumber();
            $attrs['requested_by_user_id'] = \RMS\Accounting\Support\AuditActor::userId();
        }

        return $attrs;
    }
}
