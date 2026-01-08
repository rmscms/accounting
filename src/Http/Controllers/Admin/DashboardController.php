<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\ExpenseService;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\FiscalYear;

/**
 * کنترلر داشبورد حسابداری
 */
class DashboardController extends AccountingAdminController
{
    protected LedgerService $ledgerService;
    protected CustomerInvoiceService $invoiceService;
    protected ExpenseService $expenseService;

    public function __construct(
        LedgerService $ledgerService,
        CustomerInvoiceService $invoiceService,
        ExpenseService $expenseService
    ) {
        parent::__construct();
        $this->ledgerService = $ledgerService;
        $this->invoiceService = $invoiceService;
        $this->expenseService = $expenseService;
    }

    /**
     * نمایش داشبورد حسابداری
     */
    public function index(Request $request)
    {
        // دریافت دوره مالی فعال
        $fiscalYear = FiscalYear::where('is_active', true)->first();
        
        if (!$fiscalYear) {
            return response()->json([
                'error' => trans('accounting::accounting.errors.no_active_fiscal_year')
            ], 400);
        }

        // آمار کلی
        $stats = [
            // موجودی حساب‌های اصلی
            'cash_balance' => $this->ledgerService->getBalance(
                Account::where('code', '1001')->value('id'),
                $fiscalYear->start_date,
                now()
            ),
            'bank_balance' => $this->ledgerService->getBalance(
                Account::where('code', '1002')->value('id'),
                $fiscalYear->start_date,
                now()
            ),
            
            // فاکتورهای فروش این ماه
            'monthly_invoices_count' => $this->invoiceService->getMonthlyInvoicesCount(),
            'monthly_revenue' => $this->invoiceService->getMonthlyRevenue(),
            
            // هزینه‌های این ماه
            'monthly_expenses' => $this->expenseService->getMonthlyExpenses(),
            
            // مطالبات
            'accounts_receivable' => $this->ledgerService->getAccountsReceivable(),
            
            // بدهی‌ها
            'accounts_payable' => $this->ledgerService->getAccountsPayable(),
        ];

        // نمودار فروش و هزینه 12 ماه اخیر
        $chartData = [
            'labels' => $this->getLast12MonthsLabels(),
            'revenue' => $this->invoiceService->getLast12MonthsRevenue(),
            'expenses' => $this->expenseService->getLast12MonthsExpenses(),
        ];

        // آخرین فاکتورها
        $recentInvoices = $this->invoiceService->getRecentInvoices(10);

        // آخرین هزینه‌ها
        $recentExpenses = $this->expenseService->getRecentExpenses(10);

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('dashboard.index')
            ->withPlugins(['chart-js'])
            ->withCss('vendor/accounting/admin/css/dashboard.css', true)
            ->withJs('vendor/accounting/admin/js/dashboard.js', true)
            ->with([
                'stats' => $stats,
                'chartData' => $chartData,
                'recentInvoices' => $recentInvoices,
                'recentExpenses' => $recentExpenses,
                'fiscalYear' => $fiscalYear,
            ]);
    }

    /**
     * دریافت لیبل 12 ماه اخیر
     */
    private function getLast12MonthsLabels(): array
    {
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('Y/m');
        }
        return $labels;
    }

    // متدهای abstract که باید implement بشن
    public function table(): string
    {
        return ''; // Dashboard doesn't need a table
    }

    public function modelName(): string
    {
        return ''; // Dashboard doesn't need a model
    }
}
