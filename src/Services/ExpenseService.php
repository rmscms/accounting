<?php

namespace RMS\Accounting\Services;

use Morilog\Jalali\Jalalian;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseItem;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\AuditActor;

/**
 * سرویس مدیریت هزینه‌ها و برداشت‌ها
 * - ثبت هزینه
 * - ثبت در دفتر کل
 * - طبقه‌بندی هزینه‌ها
 */
class ExpenseService
{
    protected LedgerService $ledgerService;
    protected DocumentService $documentService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
    }

    /**
     * ثبت هزینه
     */
    public function createExpense(array $data, array $items = []): Expense
    {
        DB::beginTransaction();
        try {
            // تولید شماره هزینه
            if (empty($data['expense_number'])) {
                $data['expense_number'] = $this->generateExpenseNumber($data['store_id']);
            }

            // محاسبه مجموع
            $subtotal = array_sum(array_column($items, 'total_amount'));
            $data['subtotal'] = $subtotal;
            $data['total_amount'] = $subtotal;

            // ایجاد هزینه
            $expense = Expense::create($data);

            // ثبت آیتم‌های هزینه
            foreach ($items as $item) {
                ExpenseItem::create([
                    'expense_id' => $expense->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['total_amount'],
                ]);
            }

            // ثبت در دفتر کل (اگر تایید شده)
            if ($expense->status === Expense::STATUS_APPROVED) {
                $this->recordExpenseInLedger($expense);
            }

            DB::commit();
            return $expense;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت هزینه در دفتر کل
     */
    protected function recordExpenseInLedger(Expense $expense): void
    {
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => AccountingDocument::TYPE_EXPENSE,
            'store_id' => $expense->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => AccountingDocument::REF_EVENT,
            'reference_id' => $expense->id,
            'description' => "هزینه {$expense->expense_number} - {$expense->description}",
            'total_debit' => $expense->total_amount,
            'total_credit' => $expense->total_amount,
        ]);

        // آرتیکل بدهکار: حساب هزینه
        $expenseAccountId = $this->getExpenseAccountId($expense);

        $this->ledgerService->recordEntry([
            'event_type' => 'expense',
            'event_source' => 'manual',
            'source_reference_type' => Expense::class,
            'source_reference_id' => $expense->id,
            'store_id' => $expense->store_id,
            'account_id' => $expenseAccountId,
            'currency_code' => $expense->currency_code,
            'debit_amount' => $expense->total_amount,
            'credit_amount' => 0,
            'fx_rate_to_base' => $expense->fx_rate_at_expense,
            'accounting_document_id' => $document->id,
            'description' => "هزینه {$expense->expense_number}",
        ]);

        // آرتیکل بستانکار: حساب پرداخت (بانک/صندوق)
        $paymentAccountId = $this->getPaymentAccountId($expense);

        $this->ledgerService->recordEntry([
            'event_type' => 'expense',
            'event_source' => 'manual',
            'source_reference_type' => Expense::class,
            'source_reference_id' => $expense->id,
            'store_id' => $expense->store_id,
            'account_id' => $paymentAccountId,
            'currency_code' => $expense->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $expense->total_amount,
            'fx_rate_to_base' => $expense->fx_rate_at_expense,
            'accounting_document_id' => $document->id,
            'description' => "پرداخت {$expense->expense_number}",
        ]);

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند
        $expense->update(['document_id' => $document->id]);
    }

    /**
     * ثبت ایمن و idempotent سند هزینه در دفترکل.
     */
    public function ensureLedgerPosted(Expense $expense): void
    {
        $expense->refresh();
        if (! in_array((string) $expense->status, [Expense::STATUS_APPROVED, Expense::STATUS_PAID], true)) {
            return;
        }

        if (! empty($expense->document_id)) {
            return;
        }

        $existing = AccountingDocument::query()
            ->where('document_type', AccountingDocument::TYPE_EXPENSE)
            ->where('reference_type', AccountingDocument::REF_EVENT)
            ->where('reference_id', $expense->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            if ($existing->status !== AccountingDocument::STATUS_POSTED) {
                $this->documentService->postDocument((int) $existing->id);
            }
            $expense->update(['document_id' => $existing->id]);

            return;
        }

        $this->recordExpenseInLedger($expense);
    }

    /**
     * تعیین حساب هزینه بر اساس دسته‌بندی
     */
    protected function getExpenseAccountId(Expense $expense): int
    {
        if ($expense->expense_category_id) {
            $category = \RMS\Accounting\Models\ExpenseCategory::find($expense->expense_category_id);
            if ($category && $category->account_id) {
                $categoryAccountId = (int) $category->account_id;
                if ($categoryAccountId > 0 && Account::query()->whereKey($categoryAccountId)->exists()) {
                    return $categoryAccountId;
                }
            }
        }

        $fallback = (int) config('accounting.accounts.general_expense');
        if ($fallback > 0 && Account::query()->whereKey($fallback)->exists()) {
            return $fallback;
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * تعیین حساب پرداخت
     */
    protected function getPaymentAccountId(Expense $expense): int
    {
        if ($expense->bank_id) {
            $bank = \RMS\Accounting\Models\Bank::find($expense->bank_id);
            $bankAccountId = (int) ($bank->account_id ?? 0);
            if ($bankAccountId > 0 && Account::query()->whereKey($bankAccountId)->exists()) {
                return $bankAccountId;
            }

            $bankFallback = (int) config('accounting.accounts.bank_default');
            if ($bankFallback > 0 && Account::query()->whereKey($bankFallback)->exists()) {
                return $bankFallback;
            }
        }

        if ($expense->cash_box_id) {
            $cashBox = \RMS\Accounting\Models\CashBox::find($expense->cash_box_id);
            $cashAccountId = (int) ($cashBox->account_id ?? 0);
            if ($cashAccountId > 0 && Account::query()->whereKey($cashAccountId)->exists()) {
                return $cashAccountId;
            }

            $cashFallback = (int) config('accounting.accounts.cash_box_default');
            if ($cashFallback > 0 && Account::query()->whereKey($cashFallback)->exists()) {
                return $cashFallback;
            }
        }

        if ($expense->pos_terminal_id) {
            $posFallback = (int) config('accounting.accounts.pos_terminal', config('accounting.accounts.bank_default'));
            if ($posFallback > 0 && Account::query()->whereKey($posFallback)->exists()) {
                return $posFallback;
            }
        }

        $finalFallback = (int) config('accounting.accounts.cash_box_default');
        if ($finalFallback > 0 && Account::query()->whereKey($finalFallback)->exists()) {
            return $finalFallback;
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_ASSET)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * تایید هزینه
     */
    public function approveExpense(int $expenseId): bool
    {
        DB::beginTransaction();
        try {
            $expense = Expense::findOrFail($expenseId);

            if ($expense->status === Expense::STATUS_APPROVED) {
                throw new \Exception('هزینه قبلاً تایید شده است');
            }

            $approvePayload = [
                'status' => Expense::STATUS_APPROVED,
                'approved_at' => now(),
            ];
            $approvePayload = AuditActor::stamp($approvePayload, 'expenses', 'approved');

            $expense->update($approvePayload);

            // ثبت در دفتر کل
            $this->recordExpenseInLedger($expense);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * تولید شماره هزینه
     */
    protected function generateExpenseNumber(int $storeId): string
    {
        $year = date('Y');
        $month = date('m');

        $lastExpense = Expense::where('store_id', $storeId)
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastExpense ? intval(substr($lastExpense->expense_number, -6)) + 1 : 1;

        return sprintf('EXP-%d-%s%s-%06d', $storeId, $year, $month, $nextNumber);
    }

    /**
     * گزارش هزینه‌ها بر اساس دسته‌بندی
     */
    /**
     * وضعیت‌هایی که در آمار «هزینه محقق‌شده» لحاظ می‌شوند.
     *
     * @return array<int, string>
     */
    protected function countableExpenseStatuses(): array
    {
        return [Expense::STATUS_APPROVED, Expense::STATUS_PAID];
    }

    public function getExpensesByCategory(?int $storeId = null, ?string $fromDate = null, ?string $toDate = null)
    {
        $query = Expense::select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->whereIn('status', $this->countableExpenseStatuses())
            ->groupBy('expense_category_id');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($fromDate) {
            $query->whereDate('expense_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('expense_date', '<=', $toDate);
        }

        return $query->with('category')->get();
    }

    /**
     * دریافت هزینه‌های ماه جاری
     */
    public function getMonthlyExpenses(): float
    {
        return (float) Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->whereIn('status', $this->countableExpenseStatuses())
            ->sum('amount');
    }

    /**
     * دریافت آخرین هزینه‌ها
     */
    public function getRecentExpenses(int $limit = 10)
    {
        return Expense::with('category')
            ->orderBy('expense_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * دریافت هزینه‌های 12 ماه اخیر (بر اساس سال مالی فعال)
     */
    public function getLast12MonthsExpenses(): array
    {
        $fiscalYear = \RMS\Accounting\Models\FiscalYear::where('is_current', true)->first();

        if (! $fiscalYear) {
            return array_fill(0, 12, 0);
        }

        $code = (string) $fiscalYear->year_code;
        $jalaliYear = preg_match('/(\d{4})/', $code, $m) ? (int) $m[1] : (int) \RMS\Helper\persian_date(now(), 'Y');
        if ($jalaliYear < 1300 || $jalaliYear > 1600) {
            $jalaliYear = (int) \RMS\Helper\persian_date(now(), 'Y');
        }

        $statuses = $this->countableExpenseStatuses();
        $expenses = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = new Jalalian($jalaliYear, $month, 1);
            $end = new Jalalian($jalaliYear, $month, $start->getMonthDays());
            $from = $start->toCarbon()->toDateString();
            $to = $end->toCarbon()->toDateString();

            $expenses[] = (float) Expense::query()
                ->whereBetween('expense_date', [$from, $to])
                ->whereIn('status', $statuses)
                ->sum('amount');
        }

        return $expenses;
    }
}
