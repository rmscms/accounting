<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\{
    LedgerService,
    CustomerInvoiceService,
    ExpenseService,
    AccountService
};
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر گزارش‌های مالی
 */
class ReportsController extends AdminController
{
    protected LedgerService $ledgerService;
    protected AccountService $accountService;

    public function __construct(
        LedgerService $ledgerService,
        AccountService $accountService
    ) {
        $this->ledgerService = $ledgerService;
        $this->accountService = $accountService;
    }

    /**
     * گزارش تراز آزمایشی (Trial Balance)
     */
    public function trialBalance(Request $request)
    {
        $storeId = $request->get('store_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $accounts = \RMS\Accounting\Models\Account::where('active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = $this->ledgerService->getBalance(
                accountId: $account->id,
                fromDate: $fromDate,
                toDate: $toDate,
                storeId: $storeId
            );

            if ($balance['debit'] > 0 || $balance['credit'] > 0) {
                $trialBalance[] = [
                    'account' => $account,
                    'debit' => $balance['debit'],
                    'credit' => $balance['credit'],
                ];

                $totalDebit += $balance['debit'];
                $totalCredit += $balance['credit'];
            }
        }

        return view('accounting::admin.reports.trial-balance', compact(
            'trialBalance',
            'totalDebit',
            'totalCredit',
            'fromDate',
            'toDate',
            'storeId'
        ));
    }

    /**
     * گزارش ترازنامه (Balance Sheet)
     */
    public function balanceSheet(Request $request)
    {
        $storeId = $request->get('store_id');
        $asOfDate = $request->get('as_of_date', now()->toDateString());

        // دارایی‌ها
        $assets = $this->getAccountsByType('asset', $storeId, null, $asOfDate);
        $totalAssets = array_sum(array_column($assets, 'balance'));

        // بدهی‌ها
        $liabilities = $this->getAccountsByType('liability', $storeId, null, $asOfDate);
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));

        // سرمایه
        $equity = $this->getAccountsByType('equity', $storeId, null, $asOfDate);
        $totalEquity = array_sum(array_column($equity, 'balance'));

        return view('accounting::admin.reports.balance-sheet', compact(
            'assets',
            'liabilities',
            'equity',
            'totalAssets',
            'totalLiabilities',
            'totalEquity',
            'asOfDate',
            'storeId'
        ));
    }

    /**
     * گزارش سود و زیان (Profit & Loss)
     */
    public function profitLoss(Request $request)
    {
        $storeId = $request->get('store_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // درآمدها
        $revenues = $this->getAccountsByType('revenue', $storeId, $fromDate, $toDate);
        $totalRevenue = array_sum(array_column($revenues, 'balance'));

        // هزینه‌ها
        $expenses = $this->getAccountsByType('expense', $storeId, $fromDate, $toDate);
        $totalExpense = array_sum(array_column($expenses, 'balance'));

        // سود/زیان خالص
        $netProfit = $totalRevenue - $totalExpense;

        return view('accounting::admin.reports.profit-loss', compact(
            'revenues',
            'expenses',
            'totalRevenue',
            'totalExpense',
            'netProfit',
            'fromDate',
            'toDate',
            'storeId'
        ));
    }

    /**
     * گزارش جریان وجوه نقد (Cash Flow)
     */
    public function cashFlow(Request $request)
    {
        $storeId = $request->get('store_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // دریافت‌ها
        $receipts = \RMS\Accounting\Models\CustomerPayment::where('status', 'completed')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->sum('amount');

        // پرداخت‌ها
        $payments = \RMS\Accounting\Models\Expense::where('status', 'approved')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->sum('total_amount');

        $netCashFlow = $receipts - $payments;

        return view('accounting::admin.reports.cash-flow', compact(
            'receipts',
            'payments',
            'netCashFlow',
            'fromDate',
            'toDate',
            'storeId'
        ));
    }

    /**
     * Helper: دریافت حساب‌ها بر اساس نوع
     */
    protected function getAccountsByType(string $type, ?int $storeId, ?string $fromDate, ?string $toDate): array
    {
        $accounts = \RMS\Accounting\Models\Account::where('account_type', $type)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $result = [];

        foreach ($accounts as $account) {
            $balance = $this->ledgerService->getBalance(
                accountId: $account->id,
                fromDate: $fromDate,
                toDate: $toDate,
                storeId: $storeId
            );

            $accountBalance = in_array($type, ['asset', 'expense'])
                ? $balance['debit'] - $balance['credit']
                : $balance['credit'] - $balance['debit'];

            if (abs($accountBalance) > 0.01) {
                $result[] = [
                    'account' => $account,
                    'balance' => $accountBalance,
                ];
            }
        }

        return $result;
    }

    /**
     * گزارش مطالبات (Accounts Receivable)
     */
    public function accountsReceivable(Request $request)
    {
        $storeId = $request->get('store_id');

        $receivables = \RMS\Accounting\Models\CustomerInvoice::where('payment_status', '!=', 'paid')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->orderBy('due_date')
            ->get();

        $totalReceivable = $receivables->sum('balance_due');
        $overdueReceivable = $receivables->where('due_date', '<', now())->sum('balance_due');

        return view('accounting::admin.reports.accounts-receivable', compact(
            'receivables',
            'totalReceivable',
            'overdueReceivable',
            'storeId'
        ));
    }

    /**
     * گزارش بدهی‌ها (Accounts Payable)
     */
    public function accountsPayable(Request $request)
    {
        $storeId = $request->get('store_id');

        $payables = \RMS\Accounting\Models\SupplierInvoice::where('payment_status', '!=', 'paid')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->orderBy('due_date')
            ->get();

        $totalPayable = $payables->sum('balance_due');
        $overduePayable = $payables->where('due_date', '<', now())->sum('balance_due');

        return view('accounting::admin.reports.accounts-payable', compact(
            'payables',
            'totalPayable',
            'overduePayable',
            'storeId'
        ));
    }
}
