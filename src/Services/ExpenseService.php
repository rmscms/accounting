<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseItem;
use Illuminate\Support\Facades\DB;

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
            'document_type' => 'expense',
            'store_id' => $expense->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => Expense::class,
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
            'fx_rate_to_irr' => $expense->fx_rate_at_expense,
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
            'fx_rate_to_irr' => $expense->fx_rate_at_expense,
            'accounting_document_id' => $document->id,
            'description' => "پرداخت {$expense->expense_number}",
        ]);

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند
        $expense->update(['document_id' => $document->id]);
    }

    /**
     * تعیین حساب هزینه بر اساس دسته‌بندی
     */
    protected function getExpenseAccountId(Expense $expense): int
    {
        if ($expense->expense_category_id) {
            $category = \RMS\Accounting\Models\ExpenseCategory::find($expense->expense_category_id);
            if ($category && $category->account_id) {
                return $category->account_id;
            }
        }

        return config('accounting.accounts.general_expense');
    }

    /**
     * تعیین حساب پرداخت
     */
    protected function getPaymentAccountId(Expense $expense): int
    {
        if ($expense->bank_id) {
            $bank = \RMS\Accounting\Models\Bank::find($expense->bank_id);
            return $bank->account_id ?? config('accounting.accounts.bank_default');
        }

        if ($expense->cash_box_id) {
            $cashBox = \RMS\Accounting\Models\CashBox::find($expense->cash_box_id);
            return $cashBox->account_id ?? config('accounting.accounts.cash_box_default');
        }

        return config('accounting.accounts.cash_box_default');
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

            $expense->update([
                'status' => Expense::STATUS_APPROVED,
                'approved_by_user_id' => auth()->id(),
                'approved_at' => now(),
            ]);

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
    public function getExpensesByCategory(?int $storeId = null, ?string $fromDate = null, ?string $toDate = null)
    {
        $query = Expense::select('expense_category_id', DB::raw('SUM(total_amount) as total'))
            ->where('status', Expense::STATUS_APPROVED)
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
}
