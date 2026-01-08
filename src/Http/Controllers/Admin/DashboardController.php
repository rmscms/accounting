<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\ExpenseService;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\FiscalYear;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر داشبورد حسابداری
 */
class DashboardController extends AdminController
{
    protected LedgerService $ledgerService;
    protected CustomerInvoiceService $invoiceService;
    protected ExpenseService $expenseService;

    public function __construct(
        LedgerService $ledgerService,
        CustomerInvoiceService $invoiceService,
        ExpenseService $expenseService
    ) {
        $this->ledgerService = $ledgerService;
        $this->invoiceService = $invoiceService;
        $this->expenseService = $expenseService;
    }

    /**
     * نمایش داشبورد
     */
    public function index(Request $request)
    {
        $storeId = $request->get('store_id');
        $fiscalYear = FiscalYear::where('is_current', true)->first();

        // آمار کلی
        $stats = [
            // دارایی‌ها
            'total_assets' => $this->getTotalByAccountType('asset', $storeId),
            
            // بدهی‌ها
            'total_liabilities' => $this->getTotalByAccountType('liability', $storeId),
            
            // سرمایه
            'total_equity' => $this->getTotalByAccountType('equity', $storeId),
            
            // درآمد
            'total_revenue' => $this->getTotalByAccountType('revenue', $storeId),
            
            // هزینه
            'total_expenses' => $this->getTotalByAccountType('expense', $storeId),
            
            // سود/زیان
            'profit_loss' => 0, // محاسبه می‌شود
        ];

        // محاسبه سود/زیان
        $stats['profit_loss'] = $stats['total_revenue'] - $stats['total_expenses'];

        // فاکتورهای معوق
        $overdueInvoices = $this->invoiceService->getOverdueInvoices($storeId);

        // آخرین اسناد
        $recentDocuments = \RMS\Accounting\Models\AccountingDocument::with('fiscalYear')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest()
            ->take(10)
            ->get();

        // نمودار درآمد/هزینه ماهانه
        $monthlyChart = $this->getMonthlyRevenueExpenseChart($storeId);

        return view('accounting::admin.dashboard', compact(
            'stats',
            'fiscalYear',
            'overdueInvoices',
            'recentDocuments',
            'monthlyChart'
        ));
    }

    /**
     * محاسبه مجموع بر اساس نوع حساب
     */
    protected function getTotalByAccountType(string $type, ?int $storeId = null): float
    {
        $accounts = Account::where('account_type', $type)
            ->where('active', true)
            ->pluck('id');

        $total = 0;
        foreach ($accounts as $accountId) {
            $balance = $this->ledgerService->getBalance(
                accountId: $accountId,
                storeId: $storeId
            );
            
            // برای دارایی و هزینه: بدهکار - بستانکار
            // برای بدهی، سرمایه و درآمد: بستانکار - بدهکار
            if (in_array($type, ['asset', 'expense'])) {
                $total += $balance['debit'] - $balance['credit'];
            } else {
                $total += $balance['credit'] - $balance['debit'];
            }
        }

        return $total;
    }

    /**
     * نمودار درآمد/هزینه ماهانه
     */
    protected function getMonthlyRevenueExpenseChart(?int $storeId = null): array
    {
        $months = [];
        $revenue = [];
        $expenses = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');

            // درآمد ماهانه
            $monthRevenue = \RMS\Accounting\Models\CustomerInvoice::where('status', 'issued')
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->whereYear('invoice_date', $date->year)
                ->whereMonth('invoice_date', $date->month)
                ->sum('subtotal');

            $revenue[] = $monthRevenue;

            // هزینه ماهانه
            $monthExpense = \RMS\Accounting\Models\Expense::where('status', 'approved')
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->whereYear('expense_date', $date->year)
                ->whereMonth('expense_date', $date->month)
                ->sum('total_amount');

            $expenses[] = $monthExpense;
        }

        return [
            'labels' => $months,
            'revenue' => $revenue,
            'expenses' => $expenses,
        ];
    }
}
