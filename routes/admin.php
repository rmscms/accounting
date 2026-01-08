<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\Admin;

/*
|--------------------------------------------------------------------------
| Admin Routes - Accounting Package
|--------------------------------------------------------------------------
*/

Route::prefix('admin/accounting')
    ->middleware(['web', 'auth:sanctum'])
    ->name('admin.accounting.')
    ->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // حساب‌ها (Chart of Accounts)
        Route::resource('accounts', Admin\AccountsController::class);
        Route::get('/accounts/tree', [Admin\AccountsController::class, 'tree'])->name('accounts.tree');
        Route::get('/accounts/{id}/statement', [Admin\AccountsController::class, 'statement'])->name('accounts.statement');

        // دفتر کل (Ledger)
        Route::get('/ledger', [Admin\LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/{id}', [Admin\LedgerController::class, 'show'])->name('ledger.show');
        Route::get('/ledger/export', [Admin\LedgerController::class, 'exportLedger'])->name('ledger.export');

        // اسناد حسابداری
        Route::resource('documents', Admin\DocumentsController::class);
        Route::post('/documents/{id}/post', [Admin\DocumentsController::class, 'post'])->name('documents.post');
        Route::post('/documents/{id}/reverse', [Admin\DocumentsController::class, 'reverse'])->name('documents.reverse');

        // سال‌های مالی
        Route::resource('fiscal-years', Admin\FiscalYearsController::class);
        Route::post('/fiscal-years/{id}/close', [Admin\FiscalYearsController::class, 'close'])->name('fiscal-years.close');

        // ارزها و نرخ ارز
        Route::resource('currencies', Admin\CurrenciesController::class);

        // بانک‌ها
        Route::resource('banks', Admin\BanksController::class);

        // صندوق‌ها
        Route::resource('cashboxes', Admin\CashBoxesController::class);

        // ترمینال‌های POS
        Route::resource('pos-terminals', Admin\POSTerminalsController::class);

        // روش‌های پرداخت
        Route::resource('payment-methods', Admin\PaymentMethodsController::class);

        // چک‌ها
        Route::resource('cheques', Admin\ChequesController::class);
        Route::post('/cheques/{id}/cash', [Admin\ChequesController::class, 'cash'])->name('cheques.cash');
        Route::post('/cheques/{id}/bounce', [Admin\ChequesController::class, 'bounce'])->name('cheques.bounce');

        // فاکتورهای مشتریان
        Route::resource('customer-invoices', Admin\CustomerInvoicesController::class);

        // دریافت‌های مشتریان
        Route::resource('customer-payments', Admin\CustomerPaymentsController::class);

        // تامین‌کنندگان
        Route::resource('suppliers', Admin\SuppliersController::class);

        // سفارش خرید
        Route::resource('purchase-orders', Admin\PurchaseOrdersController::class);
        Route::post('/purchase-orders/{id}/confirm', [Admin\PurchaseOrdersController::class, 'confirm'])->name('purchase-orders.confirm');

        // فاکتورهای خرید
        Route::resource('supplier-invoices', Admin\SupplierInvoicesController::class);

        // دسته‌بندی هزینه‌ها
        Route::resource('expense-categories', Admin\ExpenseCategoriesController::class);

        // هزینه‌ها
        Route::resource('expenses', Admin\ExpensesController::class);
        Route::post('/expenses/{id}/approve', [Admin\ExpensesController::class, 'approve'])->name('expenses.approve');

        // نرخ مالیات
        Route::resource('tax-rates', Admin\TaxRatesController::class);

        // تطبیق پرداخت‌ها
        Route::resource('reconciliations', Admin\ReconciliationsController::class);
        Route::post('/reconciliations/{id}/confirm', [Admin\ReconciliationsController::class, 'confirm'])->name('reconciliations.confirm');
        Route::post('/reconciliations/auto', [Admin\ReconciliationsController::class, 'autoReconcile'])->name('reconciliations.auto');

        // گزارش‌ها
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [Admin\ReportsController::class, 'index'])->name('index');
            Route::get('/trial-balance', [Admin\ReportsController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/balance-sheet', [Admin\ReportsController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/income-statement', [Admin\ReportsController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/cash-flow', [Admin\ReportsController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/accounts-receivable', [Admin\ReportsController::class, 'accountsReceivable'])->name('accounts-receivable');
            Route::get('/accounts-payable', [Admin\ReportsController::class, 'accountsPayable'])->name('accounts-payable');
            Route::get('/sales-summary', [Admin\ReportsController::class, 'salesSummary'])->name('sales-summary');
            Route::get('/expense-summary', [Admin\ReportsController::class, 'expenseSummary'])->name('expense-summary');
        });
    });
