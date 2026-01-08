<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\Admin;
use RMS\Api\Support\RouteHelper;

/*
|--------------------------------------------------------------------------
| Admin Routes - Accounting Package
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware(['web', 'auth:sanctum'])
    ->name('admin.')
    ->group(function () {
        
        // Dashboard
        Route::get('/accounting/dashboard', [Admin\DashboardController::class, 'index'])
            ->name('accounting.dashboard');

        // حساب‌ها (Chart of Accounts)
        RouteHelper::adminResource('accounting/accounts', Admin\AccountsController::class);
        Route::get('/accounting/accounts/tree', [Admin\AccountsController::class, 'tree'])
            ->name('accounting.accounts.tree');
        Route::get('/accounting/accounts/{id}/statement', [Admin\AccountsController::class, 'statement'])
            ->name('accounting.accounts.statement');

        // دفتر کل (Ledger)
        Route::get('/accounting/ledger', [Admin\LedgerController::class, 'index'])
            ->name('accounting.ledger.index');
        Route::get('/accounting/ledger/export', [Admin\LedgerController::class, 'export'])
            ->name('accounting.ledger.export');

        // اسناد حسابداری
        RouteHelper::adminResource('accounting/documents', Admin\DocumentsController::class);
        Route::post('/accounting/documents/{id}/post', [Admin\DocumentsController::class, 'post'])
            ->name('accounting.documents.post');
        Route::post('/accounting/documents/{id}/reverse', [Admin\DocumentsController::class, 'reverse'])
            ->name('accounting.documents.reverse');

        // سال‌های مالی
        RouteHelper::adminResource('accounting/fiscal-years', Admin\FiscalYearsController::class);
        Route::post('/accounting/fiscal-years/{id}/close', [Admin\FiscalYearsController::class, 'close'])
            ->name('accounting.fiscal-years.close');

        // ارزها و نرخ ارز
        RouteHelper::adminResource('accounting/currencies', Admin\CurrenciesController::class);
        RouteHelper::adminResource('accounting/currency-rates', Admin\CurrencyRatesController::class);

        // بانک‌ها
        RouteHelper::adminResource('accounting/banks', Admin\BanksController::class);

        // صندوق‌ها
        RouteHelper::adminResource('accounting/cash-boxes', Admin\CashBoxesController::class);

        // ترمینال‌های POS
        RouteHelper::adminResource('accounting/pos-terminals', Admin\POSTerminalsController::class);

        // روش‌های پرداخت
        RouteHelper::adminResource('accounting/payment-methods', Admin\PaymentMethodsController::class);

        // چک‌ها
        RouteHelper::adminResource('accounting/cheques', Admin\ChequesController::class);
        Route::post('/accounting/cheques/{id}/cash', [Admin\ChequesController::class, 'cash'])
            ->name('accounting.cheques.cash');
        Route::post('/accounting/cheques/{id}/bounce', [Admin\ChequesController::class, 'bounce'])
            ->name('accounting.cheques.bounce');

        // کیف پول‌ها
        RouteHelper::adminResource('accounting/wallets', Admin\WalletsController::class);

        // فاکتورهای مشتریان
        RouteHelper::adminResource('accounting/customer-invoices', Admin\CustomerInvoicesController::class);

        // دریافت‌های مشتریان
        RouteHelper::adminResource('accounting/customer-payments', Admin\CustomerPaymentsController::class);

        // مانده مشتریان
        Route::get('/accounting/customer-balances', [Admin\CustomerBalancesController::class, 'index'])
            ->name('accounting.customer-balances.index');

        // تامین‌کنندگان
        RouteHelper::adminResource('accounting/suppliers', Admin\SuppliersController::class);

        // سفارش خرید
        RouteHelper::adminResource('accounting/purchase-orders', Admin\PurchaseOrdersController::class);
        Route::post('/accounting/purchase-orders/{id}/confirm', [Admin\PurchaseOrdersController::class, 'confirm'])
            ->name('accounting.purchase-orders.confirm');

        // فاکتورهای خرید
        RouteHelper::adminResource('accounting/supplier-invoices', Admin\SupplierInvoicesController::class);

        // پرداخت‌های تامین‌کنندگان
        RouteHelper::adminResource('accounting/supplier-payments', Admin\SupplierPaymentsController::class);

        // دسته‌بندی هزینه‌ها
        RouteHelper::adminResource('accounting/expense-categories', Admin\ExpenseCategoriesController::class);

        // هزینه‌ها
        RouteHelper::adminResource('accounting/expenses', Admin\ExpensesController::class);
        Route::post('/accounting/expenses/{id}/approve', [Admin\ExpensesController::class, 'approve'])
            ->name('accounting.expenses.approve');

        // نرخ مالیات
        RouteHelper::adminResource('accounting/tax-rates', Admin\TaxRatesController::class);

        // تطبیق پرداخت‌ها
        RouteHelper::adminResource('accounting/reconciliations', Admin\ReconciliationsController::class);
        Route::post('/accounting/reconciliations/{id}/confirm', [Admin\ReconciliationsController::class, 'confirm'])
            ->name('accounting.reconciliations.confirm');
        Route::post('/accounting/reconciliations/auto', [Admin\ReconciliationsController::class, 'autoReconcile'])
            ->name('accounting.reconciliations.auto');

        // تسویه‌ها
        RouteHelper::adminResource('accounting/settlements', Admin\SettlementsController::class);
        Route::post('/accounting/settlements/{id}/complete', [Admin\SettlementsController::class, 'complete'])
            ->name('accounting.settlements.complete');

        // گزارش‌ها
        Route::prefix('accounting/reports')->name('accounting.reports.')->group(function () {
            Route::get('/trial-balance', [Admin\ReportsController::class, 'trialBalance'])
                ->name('trial-balance');
            Route::get('/balance-sheet', [Admin\ReportsController::class, 'balanceSheet'])
                ->name('balance-sheet');
            Route::get('/profit-loss', [Admin\ReportsController::class, 'profitLoss'])
                ->name('profit-loss');
            Route::get('/cash-flow', [Admin\ReportsController::class, 'cashFlow'])
                ->name('cash-flow');
            Route::get('/accounts-receivable', [Admin\ReportsController::class, 'accountsReceivable'])
                ->name('accounts-receivable');
            Route::get('/accounts-payable', [Admin\ReportsController::class, 'accountsPayable'])
                ->name('accounts-payable');
            Route::get('/sales-summary', [Admin\ReportsController::class, 'salesSummary'])
                ->name('sales-summary');
            Route::get('/expense-summary', [Admin\ReportsController::class, 'expenseSummary'])
                ->name('expense-summary');
        });
    });
