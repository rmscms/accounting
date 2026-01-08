<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\ReportService;

/**
 * کنترلر گزارش‌های مالی
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

    /**
     * گزارش تراز آزمایشی
     */
    public function trialBalance(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTrialBalance($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.trial-balance')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * ترازنامه
     */
    public function balanceSheet(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBalanceSheet($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.balance-sheet')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * صورت سود و زیان
     */
    public function incomeStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getIncomeStatement($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.income-statement')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * گزارش جریان وجوه نقد
     */
    public function cashFlow(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashFlow($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.cash-flow')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * گزارش حساب‌های دریافتنی
     */
    public function accountsReceivable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsReceivable($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.accounts-receivable')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * گزارش حساب‌های پرداختنی
     */
    public function accountsPayable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsPayable($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.accounts-payable')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * خلاصه فروش
     */
    public function salesSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesSummary($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.sales-summary')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->with(['data' => $data]);
    }

    /**
     * خلاصه هزینه‌ها
     */
    public function expenseSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseSummary($request->all());

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.expense-summary')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
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
