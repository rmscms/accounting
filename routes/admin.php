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

        // دفتر کل و اسناد
        Route::resource('ledger', Admin\LedgerController::class)->only(['index', 'show']);
        Route::resource('documents', Admin\DocumentsController::class);
        
        // فاکتورها و دریافت‌ها
        Route::resource('customer-invoices', Admin\CustomerInvoicesController::class);
        Route::resource('customer-payments', Admin\CustomerPaymentsController::class);
        
        // هزینه‌ها
        Route::resource('expenses', Admin\ExpensesController::class);
        
        // تطبیق پرداخت‌ها
        Route::resource('reconciliations', Admin\ReconciliationsController::class);
        
        // خزانه‌داری
        Route::resource('banks', Admin\BanksController::class);
        Route::resource('cashboxes', Admin\CashBoxesController::class);
        Route::resource('pos-terminals', Admin\POSTerminalsController::class);
        Route::resource('payment-methods', Admin\PaymentMethodsController::class);
        Route::resource('cheques', Admin\ChequesController::class);
        
        // تنظیمات
        Route::resource('currencies', Admin\CurrenciesController::class);
        Route::resource('tax-rates', Admin\TaxRatesController::class);
        Route::resource('fiscal-years', Admin\FiscalYearsController::class);
        Route::resource('suppliers', Admin\SuppliersController::class);
        
        // سفارش‌های خرید
        Route::resource('purchase-orders', Admin\PurchaseOrdersController::class);
        Route::resource('supplier-invoices', Admin\SupplierInvoicesController::class);
        
        // گزارش‌ها
        Route::get('/reports', [Admin\ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/balance-sheet', [Admin\ReportsController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('/reports/income-statement', [Admin\ReportsController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('/reports/cash-flow', [Admin\ReportsController::class, 'cashFlow'])->name('reports.cash-flow');
    });
