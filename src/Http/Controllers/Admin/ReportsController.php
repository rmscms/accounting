<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\ReportService;

/**
 * کنترلر گزارش‌های مالی
 * 70+ گزارش مالی جامع
 */
class ReportsController extends AccountingAdminController
{
    /**
     * صفحه اصلی گزارش‌ها
     */
    public function index(Request $request)
    {
        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.index')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withJs('vendor/accounting/admin/js/reports.js', true);
    }

    // ==========================================
    // گزارش‌های مالی اصلی (Core Financial)
    // ==========================================

    /**
     * گزارش دفتر کل
     */
    public function generalLedger(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getGeneralLedger($request->all());

        return $this->renderReport('general-ledger', $data);
    }

    /**
     * گزارش دفتر معین
     */
    public function subsidiaryLedger(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSubsidiaryLedger($request->all());

        return $this->renderReport('subsidiary-ledger', $data);
    }

    /**
     * گزارش تراز آزمایشی
     */
    public function trialBalance(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTrialBalance($request->all());

        return $this->renderReport('trial-balance', $data);
    }

    /**
     * ترازنامه
     */
    public function balanceSheet(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBalanceSheet($request->all());

        return $this->renderReport('balance-sheet', $data);
    }

    /**
     * صورت سود و زیان
     */
    public function incomeStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getIncomeStatement($request->all());

        return $this->renderReport('income-statement', $data);
    }

    /**
     * صورت سود و زیان (نام جایگزین)
     */
    public function profitLoss(Request $request)
    {
        return $this->incomeStatement($request);
    }

    /**
     * گزارش جریان وجوه نقد
     */
    public function cashFlow(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashFlow($request->all());

        return $this->renderReport('cash-flow', $data);
    }

    // ==========================================
    // گزارش‌های دریافتنی (AR)
    // ==========================================

    /**
     * گزارش حساب‌های دریافتنی
     */
    public function accountsReceivable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsReceivable($request->all());

        return $this->renderReport('accounts-receivable', $data);
    }

    /**
     * مانده حساب مشتریان
     */
    public function customerBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerBalances($request->all());

        return $this->renderReport('customer-balances', $data);
    }

    /**
     * گردش حساب مشتری
     */
    public function customerStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerStatement($request->all());

        return $this->renderReport('customer-statement', $data);
    }

    /**
     * مشتریان بدهکار (سررسید شده)
     */
    public function overdueCustomers(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getOverdueCustomers($request->all());

        return $this->renderReport('overdue-customers', $data);
    }

    /**
     * سن بدهی مشتریان (Aging Analysis)
     */
    public function agingAnalysisAR(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAgingAnalysisAR($request->all());

        return $this->renderReport('aging-analysis-ar', $data);
    }

    /**
     * تاریخچه فاکتورهای مشتری
     */
    public function customerInvoicesHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerInvoicesHistory($request->all());

        return $this->renderReport('customer-invoices-history', $data);
    }

    /**
     * تاریخچه پرداخت‌های دریافتی
     */
    public function paymentsReceivedHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPaymentsReceivedHistory($request->all());

        return $this->renderReport('payments-received-history', $data);
    }

    // ==========================================
    // گزارش‌های پرداختنی (AP)
    // ==========================================

    /**
     * گزارش حساب‌های پرداختنی
     */
    public function accountsPayable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsPayable($request->all());

        return $this->renderReport('accounts-payable', $data);
    }

    /**
     * مانده حساب تامین‌کنندگان
     */
    public function supplierBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierBalances($request->all());

        return $this->renderReport('supplier-balances', $data);
    }

    /**
     * گردش حساب تامین‌کننده
     */
    public function supplierStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierStatement($request->all());

        return $this->renderReport('supplier-statement', $data);
    }

    /**
     * بدهی‌های سررسید شده
     */
    public function overduePayables(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getOverduePayables($request->all());

        return $this->renderReport('overdue-payables', $data);
    }

    /**
     * سن بدهی به تامین‌کنندگان
     */
    public function agingAnalysisAP(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAgingAnalysisAP($request->all());

        return $this->renderReport('aging-analysis-ap', $data);
    }

    /**
     * تاریخچه سفارش‌های خرید
     */
    public function purchaseOrdersHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPurchaseOrdersHistory($request->all());

        return $this->renderReport('purchase-orders-history', $data);
    }

    /**
     * تاریخچه فاکتورهای خرید
     */
    public function supplierInvoicesHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierInvoicesHistory($request->all());

        return $this->renderReport('supplier-invoices-history', $data);
    }

    /**
     * تاریخچه پرداخت‌های انجام شده
     */
    public function paymentsMadeHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPaymentsMadeHistory($request->all());

        return $this->renderReport('payments-made-history', $data);
    }

    // ==========================================
    // گزارش‌های خزانه‌داری (Treasury)
    // ==========================================

    /**
     * موجودی بانک‌ها
     */
    public function bankBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankBalances($request->all());

        return $this->renderReport('bank-balances', $data);
    }

    /**
     * موجودی صندوق‌ها
     */
    public function cashboxBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashboxBalances($request->all());

        return $this->renderReport('cashbox-balances', $data);
    }

    /**
     * گزارش تراکنش‌های بانکی
     */
    public function bankTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankTransactions($request->all());

        return $this->renderReport('bank-transactions', $data);
    }

    /**
     * گزارش تراکنش‌های نقدی
     */
    public function cashTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashTransactions($request->all());

        return $this->renderReport('cash-transactions', $data);
    }

    /**
     * گزارش چک‌های دریافتی
     */
    public function chequesReceived(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequesReceived($request->all());

        return $this->renderReport('cheques-received', $data);
    }

    /**
     * گزارش چک‌های پرداختی
     */
    public function chequesIssued(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequesIssued($request->all());

        return $this->renderReport('cheques-issued', $data);
    }

    /**
     * چک‌های سررسید
     */
    public function chequeReminders(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequeReminders($request->all());

        return $this->renderReport('cheque-reminders', $data);
    }

    /**
     * گزارش POS
     */
    public function posReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPOSReport($request->all());

        return $this->renderReport('pos-report', $data);
    }

    /**
     * گزارش کیف پول‌ها
     */
    public function walletReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getWalletReport($request->all());

        return $this->renderReport('wallet-report', $data);
    }

    // ==========================================
    // گزارش‌های مالیاتی (Tax)
    // ==========================================

    /**
     * گزارش مالیات بر ارزش افزوده
     */
    public function vatReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATReport($request->all());

        return $this->renderReport('vat-report', $data);
    }

    /**
     * مالیات پرداختی
     */
    public function vatPayable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATPayable($request->all());

        return $this->renderReport('vat-payable', $data);
    }

    /**
     * مالیات دریافتنی
     */
    public function vatReceivable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATReceivable($request->all());

        return $this->renderReport('vat-receivable', $data);
    }

    /**
     * گزارش مالیات بر درآمد
     */
    public function incomeTaxReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getIncomeTaxReport($request->all());

        return $this->renderReport('income-tax-report', $data);
    }

    /**
     * گزارش معاملات مشمول مالیات
     */
    public function taxableTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTaxableTransactions($request->all());

        return $this->renderReport('taxable-transactions', $data);
    }

    // ==========================================
    // گزارش‌های هزینه (Expense)
    // ==========================================

    /**
     * خلاصه هزینه‌ها
     */
    public function expenseSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseSummary($request->all());

        return $this->renderReport('expense-summary', $data);
    }

    /**
     * گزارش هزینه‌های ماهانه
     */
    public function expenseMonthly(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseMonthly($request->all());

        return $this->renderReport('expense-monthly', $data);
    }

    /**
     * گزارش هزینه به تفکیک دسته
     */
    public function expenseByCategory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseByCategory($request->all());

        return $this->renderReport('expense-by-category', $data);
    }

    /**
     * گزارش هزینه‌های تکرارشونده
     */
    public function recurringExpenses(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getRecurringExpenses($request->all());

        return $this->renderReport('recurring-expenses', $data);
    }

    /**
     * مقایسه هزینه واقعی با بودجه
     */
    public function expenseVsBudget(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseVsBudget($request->all());

        return $this->renderReport('expense-vs-budget', $data);
    }

    /**
     * بزرگ‌ترین هزینه‌ها
     */
    public function topExpenses(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTopExpenses($request->all());

        return $this->renderReport('top-expenses', $data);
    }

    // ==========================================
    // گزارش‌های ارزی (Currency/FX)
    // ==========================================

    /**
     * گزارش تراکنش‌های ارزی
     */
    public function currencyTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCurrencyTransactions($request->all());

        return $this->renderReport('currency-transactions', $data);
    }

    /**
     * اختلاف تسعیر ارز (FX Gain/Loss)
     */
    public function fxGainLoss(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFXGainLoss($request->all());

        return $this->renderReport('fx-gain-loss', $data);
    }

    /**
     * نرخ‌های تبدیل استفاده شده
     */
    public function fxRatesUsed(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFXRatesUsed($request->all());

        return $this->renderReport('fx-rates-used', $data);
    }

    /**
     * گزارش خریدهای ارزی
     */
    public function foreignPurchases(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getForeignPurchases($request->all());

        return $this->renderReport('foreign-purchases', $data);
    }

    /**
     * مانده‌های ارزی
     */
    public function currencyBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCurrencyBalances($request->all());

        return $this->renderReport('currency-balances', $data);
    }

    // ==========================================
    // گزارش‌های COGS
    // ==========================================

    /**
     * بهای تمام شده کالای فروش رفته
     */
    public function cogsReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCOGSReport($request->all());

        return $this->renderReport('cogs-report', $data);
    }

    /**
     * تحلیل سودآوری محصولات
     */
    public function productProfitability(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getProductProfitability($request->all());

        return $this->renderReport('product-profitability', $data);
    }

    /**
     * مقایسه قیمت فروش و COGS
     */
    public function salesVsCogs(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesVsCOGS($request->all());

        return $this->renderReport('sales-vs-cogs', $data);
    }

    /**
     * روند COGS ماهانه
     */
    public function cogsMonthlyTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCOGSMonthlyTrend($request->all());

        return $this->renderReport('cogs-monthly-trend', $data);
    }

    // ==========================================
    // گزارش‌های فروش (Sales)
    // ==========================================

    /**
     * خلاصه فروش
     */
    public function salesSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesSummary($request->all());

        return $this->renderReport('sales-summary', $data);
    }

    /**
     * فروش به تفکیک مشتری
     */
    public function salesByCustomer(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesByCustomer($request->all());

        return $this->renderReport('sales-by-customer', $data);
    }

    /**
     * فروش به تفکیک محصول
     */
    public function salesByProduct(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesByProduct($request->all());

        return $this->renderReport('sales-by-product', $data);
    }

    /**
     * روند فروش
     */
    public function salesTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesTrend($request->all());

        return $this->renderReport('sales-trend', $data);
    }

    // ==========================================
    // گزارش‌های تطبیق (Reconciliation)
    // ==========================================

    /**
     * گزارش تطبیق بانک
     */
    public function bankReconciliation(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankReconciliation($request->all());

        return $this->renderReport('bank-reconciliation', $data);
    }

    /**
     * گزارش تطبیق صندوق
     */
    public function cashboxReconciliation(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashboxReconciliation($request->all());

        return $this->renderReport('cashbox-reconciliation', $data);
    }

    /**
     * اختلافات تطبیق نشده
     */
    public function unreconciledItems(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getUnreconciledItems($request->all());

        return $this->renderReport('unreconciled-items', $data);
    }

    /**
     * تاریخچه تطبیق‌ها
     */
    public function reconciliationHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getReconciliationHistory($request->all());

        return $this->renderReport('reconciliation-history', $data);
    }

    // ==========================================
    // گزارش‌های تحلیلی (Analytics)
    // ==========================================

    /**
     * پیش‌بینی نقدینگی
     */
    public function cashFlowForecast(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashFlowForecast($request->all());

        return $this->renderReport('cash-flow-forecast', $data);
    }

    /**
     * نسبت‌های مالی
     */
    public function financialRatios(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFinancialRatios($request->all());

        return $this->renderReport('financial-ratios', $data);
    }

    /**
     * تحلیل سودآوری
     */
    public function profitabilityAnalysis(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getProfitabilityAnalysis($request->all());

        return $this->renderReport('profitability-analysis', $data);
    }

    /**
     * تحلیل روند درآمد
     */
    public function revenueTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getRevenueTrend($request->all());

        return $this->renderReport('revenue-trend', $data);
    }

    /**
     * مقایسه دوره‌ای
     */
    public function periodComparison(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPeriodComparison($request->all());

        return $this->renderReport('period-comparison', $data);
    }

    // ==========================================
    // گزارش‌های سال مالی
    // ==========================================

    /**
     * عملکرد سال مالی
     */
    public function fiscalYearPerformance(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFiscalYearPerformance($request->all());

        return $this->renderReport('fiscal-year-performance', $data);
    }

    /**
     * مقایسه سال جاری با سال قبل
     */
    public function yearOverYear(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getYearOverYear($request->all());

        return $this->renderReport('year-over-year', $data);
    }

    /**
     * گزارش بستن حساب‌ها
     */
    public function closingReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getClosingReport($request->all());

        return $this->renderReport('closing-report', $data);
    }

    // ==========================================
    // گزارش‌های Audit
    // ==========================================

    /**
     * تاریخچه تغییرات
     */
    public function auditTrail(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAuditTrail($request->all());

        return $this->renderReport('audit-trail', $data);
    }

    /**
     * گزارش سندهای اصلاحی
     */
    public function documentReversals(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getDocumentReversals($request->all());

        return $this->renderReport('document-reversals', $data);
    }

    /**
     * Log فعالیت‌های حسابداری
     */
    public function accountingActivityLog(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountingActivityLog($request->all());

        return $this->renderReport('accounting-activity-log', $data);
    }

    /**
     * گزارش مغایرت‌ها
     */
    public function discrepancies(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getDiscrepancies($request->all());

        return $this->renderReport('discrepancies', $data);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * متد کمکی برای render گزارش‌ها
     */
    protected function renderReport(string $template, array $data)
    {
        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl("reports.{$template}")
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withJs('vendor/accounting/admin/js/reports.js', true)
            ->with(['data' => $data]);
    }

    // متدهای abstract
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}
