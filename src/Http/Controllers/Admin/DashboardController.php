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
    /**
     * نمایش داشبورد حسابداری
     */
    public function index(Request $request)
    {
        $ledgerService = app(LedgerService::class);
        $invoiceService = app(CustomerInvoiceService::class);
        $expenseService = app(ExpenseService::class);

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
            'cash_balance' => $ledgerService->getBalance(
                Account::where('code', '1001')->value('id'),
                $fiscalYear->start_date,
                now()
            ),
            'bank_balance' => $ledgerService->getBalance(
                Account::where('code', '1002')->value('id'),
                $fiscalYear->start_date,
                now()
            ),
            
            // فاکتورهای فروش این ماه
            'monthly_invoices_count' => $invoiceService->getMonthlyInvoicesCount(),
            'monthly_revenue' => $invoiceService->getMonthlyRevenue(),
            
            // هزینه‌های این ماه
            'monthly_expenses' => $expenseService->getMonthlyExpenses(),
            
            // مطالبات
            'accounts_receivable' => $ledgerService->getAccountsReceivable(),
            
            // بدهی‌ها
            'accounts_payable' => $ledgerService->getAccountsPayable(),
        ];

        // نمودار فروش و هزینه 12 ماه اخیر
        $chartData = [
            'labels' => $this->getLast12MonthsLabels(),
            'revenue' => $invoiceService->getLast12MonthsRevenue(),
            'expenses' => $expenseService->getLast12MonthsExpenses(),
        ];

        // آخرین فاکتورها
        $recentInvoices = $invoiceService->getRecentInvoices(10);

        // آخرین هزینه‌ها
        $recentExpenses = $expenseService->getRecentExpenses(10);

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
