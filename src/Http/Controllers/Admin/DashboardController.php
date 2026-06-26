<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\AccountingInstallService;
use RMS\Accounting\Services\AccountingReadinessService;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Services\ChequeClearingAccountSetupService;
use RMS\Accounting\Services\ChequeLedgerService;
use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\ExpenseService;
use RMS\Accounting\Services\FiscalYearService;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\SupplierAdvance;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Models\SupplierRefund;

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
        /** @var AccountingInstallService $install */
        $install = app(AccountingInstallService::class);
        if ($install->isWizardRequired() && ! $install->isComplete()) {
            return redirect()->route('admin.accounting.install');
        }

        $ledgerService = app(LedgerService::class);
        $invoiceService = app(CustomerInvoiceService::class);
        $expenseService = app(ExpenseService::class);

        /** @var FiscalYearService $fiscalYearService */
        $fiscalYearService = app(FiscalYearService::class);
        $fiscalYear = $fiscalYearService->getOrCreateCurrentFiscalYear();

        /** @var ChequeLedgerService $chequeLedgerService */
        $chequeLedgerService = app(ChequeLedgerService::class);
        $setupWarnings = [
            'missing_receivable_clearing' => $chequeLedgerService->resolveReceivableClearingAccountId() === null,
            'missing_payable_clearing' => $chequeLedgerService->resolvePayableClearingAccountId() === null,
            'suggested_code_receivable' => (string) config('accounting.system_accounts.assets.cheques_receivable_clearing', ''),
            'suggested_code_payable' => (string) config('accounting.system_accounts.liabilities.cheques_payable_clearing', ''),
        ];
        $chequeMethodId = (int) PaymentMethod::query()
            ->where('type', PaymentMethod::TYPE_CHEQUE)
            ->orderBy('id')
            ->value('id');
        $chequeDataWarnings = [
            'issued_missing_chequebook' => (int) Cheque::query()
                ->where('cheque_type', Cheque::TYPE_ISSUED)
                ->whereNull('chequebook_id')
                ->count(),
            'incomplete_linked_cheques' => (int) Cheque::query()
                ->whereNotNull('source_type')
                ->where(function ($q): void {
                    $q->whereNull('party_id')
                        ->orWhereNull('bank_id')
                        ->orWhereNull('due_date')
                        ->orWhere('amount', '<=', 0);
                })
                ->count(),
            'payments_missing_cheque_link' => $chequeMethodId > 0
                ? (
                    (int) CustomerPayment::query()->where('payment_method_id', $chequeMethodId)->whereNull('cheque_id')->count()
                    + (int) SupplierPayment::query()->where('payment_method_id', $chequeMethodId)->whereNull('cheque_id')->count()
                    + (int) SupplierAdvance::query()->where('payment_method_id', $chequeMethodId)->whereNull('cheque_id')->count()
                    + (int) SupplierRefund::query()->where('payment_method_id', $chequeMethodId)->whereNull('cheque_id')->count()
                )
                : 0,
        ];

        // آمار کلی
        $stats = [
            // موجودی حساب‌های اصلی
            'cash_balance' => $this->getAccountBalance('1-1-1'),
            'bank_balance' => $this->getAccountBalance('1-1-2'),
            
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

        /** @var AccountingReadinessService $readiness */
        $readiness = app(AccountingReadinessService::class);
        $readinessSummary = $readiness->summary();

        $this->view->usePackageNamespace('accounting')
            ->setTpl('dashboard')
            ->withPlugins(['chart-js'])
            ->withCss('vendor/accounting/admin/css/dashboard.css', true)
            ->withJs('vendor/accounting/admin/js/dashboard.js', true)
            ->withVariables([
                'stats' => $stats,
                'chartData' => $chartData,
                'recentInvoices' => $recentInvoices,
                'recentExpenses' => $recentExpenses,
                'fiscalYear' => $fiscalYear,
                'setupWarnings' => $setupWarnings,
                'chequeDataWarnings' => $chequeDataWarnings,
                'readinessSummary' => $readinessSummary,
            ]);
        
        return $this->view();
    }

    /**
     * ایجاد حساب‌های انتظامی چک و به‌روزرسانی .env (همان منطق دستور accounting:setup-cheque-clearing-accounts)
     */
    public function setupChequeClearingAccounts(Request $request)
    {
        if (! config('accounting.allow_dashboard_cheque_clearing_setup', true)) {
            abort(403);
        }

        /** @var ChequeClearingAccountSetupService $setup */
        $setup = app(ChequeClearingAccountSetupService::class);

        try {
            $r = $setup->run(writeEnv: true);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.accounting.dashboard')
                ->with('error', $e->getMessage());
        }

        $base = trans('accounting::accounting.setup.cheque_clearing.flash_success', [
            'ar' => $r['receivable_account_id'],
            'ap' => $r['payable_account_id'],
        ]);

        if ($r['env_written']) {
            return redirect()
                ->route('admin.accounting.dashboard')
                ->with('success', $base);
        }

        $hint = $r['env_error']
            ? ' ' . trans('accounting::accounting.setup.cheque_clearing.flash_env_error', ['message' => $r['env_error']])
            : '';

        return redirect()
            ->route('admin.accounting.dashboard')
            ->with('warning', $base . $hint . ' ' . trans('accounting::accounting.setup.cheque_clearing.flash_env_manual'));
    }

    /**
     * دریافت لیبل 12 ماه (بر اساس سال مالی فعال)
     */
    private function getLast12MonthsLabels(): array
    {
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        
        if (!$fiscalYear) {
            return [];
        }

        // استخراج سال شمسی از year_code (مثلاً 1403)
        $jalaliYear = $fiscalYear->year_code;
        
        $labels = [];
        $monthNames = [
            '01' => 'فروردین',
            '02' => 'اردیبهشت', 
            '03' => 'خرداد',
            '04' => 'تیر',
            '05' => 'مرداد',
            '06' => 'شهریور',
            '07' => 'مهر',
            '08' => 'آبان',
            '09' => 'آذر',
            '10' => 'دی',
            '11' => 'بهمن',
            '12' => 'اسفند',
        ];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $labels[] = $monthNames[$monthStr];
        }
        
        return $labels;
    }

    /**
     * دریافت موجودی حساب بر اساس کد
     */
    private function getAccountBalance(string $accountCode): float
    {
        $account = Account::where('code', $accountCode)->first();
        if (!$account) {
            return 0;
        }

        $ledgerService = app(LedgerService::class);
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        
        return $ledgerService->getBalance(
            $account->id,
            $fiscalYear->start_date ?? null,
            now()
        );
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
