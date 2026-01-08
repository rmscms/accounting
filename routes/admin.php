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
            
            // گزارش‌های مالی اصلی (Core Financial)
            Route::get('/general-ledger', [Admin\ReportsController::class, 'generalLedger'])->name('general-ledger');
            Route::get('/subsidiary-ledger', [Admin\ReportsController::class, 'subsidiaryLedger'])->name('subsidiary-ledger');
            Route::get('/trial-balance', [Admin\ReportsController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/balance-sheet', [Admin\ReportsController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/income-statement', [Admin\ReportsController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/profit-loss', [Admin\ReportsController::class, 'profitLoss'])->name('profit-loss');
            Route::get('/cash-flow', [Admin\ReportsController::class, 'cashFlow'])->name('cash-flow');
            
            // گزارش‌های دریافتنی (AR)
            Route::get('/accounts-receivable', [Admin\ReportsController::class, 'accountsReceivable'])->name('accounts-receivable');
            Route::get('/customer-balances', [Admin\ReportsController::class, 'customerBalances'])->name('customer-balances');
            Route::get('/customer-statement', [Admin\ReportsController::class, 'customerStatement'])->name('customer-statement');
            Route::get('/overdue-customers', [Admin\ReportsController::class, 'overdueCustomers'])->name('overdue-customers');
            Route::get('/aging-analysis-ar', [Admin\ReportsController::class, 'agingAnalysisAR'])->name('aging-analysis-ar');
            Route::get('/customer-invoices-history', [Admin\ReportsController::class, 'customerInvoicesHistory'])->name('customer-invoices-history');
            Route::get('/payments-received-history', [Admin\ReportsController::class, 'paymentsReceivedHistory'])->name('payments-received-history');
            
            // گزارش‌های پرداختنی (AP)
            Route::get('/accounts-payable', [Admin\ReportsController::class, 'accountsPayable'])->name('accounts-payable');
            Route::get('/supplier-balances', [Admin\ReportsController::class, 'supplierBalances'])->name('supplier-balances');
            Route::get('/supplier-statement', [Admin\ReportsController::class, 'supplierStatement'])->name('supplier-statement');
            Route::get('/overdue-payables', [Admin\ReportsController::class, 'overduePayables'])->name('overdue-payables');
            Route::get('/aging-analysis-ap', [Admin\ReportsController::class, 'agingAnalysisAP'])->name('aging-analysis-ap');
            Route::get('/purchase-orders-history', [Admin\ReportsController::class, 'purchaseOrdersHistory'])->name('purchase-orders-history');
            Route::get('/supplier-invoices-history', [Admin\ReportsController::class, 'supplierInvoicesHistory'])->name('supplier-invoices-history');
            Route::get('/payments-made-history', [Admin\ReportsController::class, 'paymentsMadeHistory'])->name('payments-made-history');
            
            // گزارش‌های خزانه‌داری (Treasury)
            Route::get('/bank-balances', [Admin\ReportsController::class, 'bankBalances'])->name('bank-balances');
            Route::get('/cashbox-balances', [Admin\ReportsController::class, 'cashboxBalances'])->name('cashbox-balances');
            Route::get('/bank-transactions', [Admin\ReportsController::class, 'bankTransactions'])->name('bank-transactions');
            Route::get('/cash-transactions', [Admin\ReportsController::class, 'cashTransactions'])->name('cash-transactions');
            Route::get('/cheques-received', [Admin\ReportsController::class, 'chequesReceived'])->name('cheques-received');
            Route::get('/cheques-issued', [Admin\ReportsController::class, 'chequesIssued'])->name('cheques-issued');
            Route::get('/cheque-reminders', [Admin\ReportsController::class, 'chequeReminders'])->name('cheque-reminders');
            Route::get('/pos-report', [Admin\ReportsController::class, 'posReport'])->name('pos-report');
            Route::get('/wallet-report', [Admin\ReportsController::class, 'walletReport'])->name('wallet-report');
            
            // گزارش‌های مالیاتی (Tax)
            Route::get('/vat-report', [Admin\ReportsController::class, 'vatReport'])->name('vat-report');
            Route::get('/vat-payable', [Admin\ReportsController::class, 'vatPayable'])->name('vat-payable');
            Route::get('/vat-receivable', [Admin\ReportsController::class, 'vatReceivable'])->name('vat-receivable');
            Route::get('/income-tax-report', [Admin\ReportsController::class, 'incomeTaxReport'])->name('income-tax-report');
            Route::get('/taxable-transactions', [Admin\ReportsController::class, 'taxableTransactions'])->name('taxable-transactions');
            
            // گزارش‌های هزینه (Expense)
            Route::get('/expense-summary', [Admin\ReportsController::class, 'expenseSummary'])->name('expense-summary');
            Route::get('/expense-monthly', [Admin\ReportsController::class, 'expenseMonthly'])->name('expense-monthly');
            Route::get('/expense-by-category', [Admin\ReportsController::class, 'expenseByCategory'])->name('expense-by-category');
            Route::get('/recurring-expenses', [Admin\ReportsController::class, 'recurringExpenses'])->name('recurring-expenses');
            Route::get('/expense-vs-budget', [Admin\ReportsController::class, 'expenseVsBudget'])->name('expense-vs-budget');
            Route::get('/top-expenses', [Admin\ReportsController::class, 'topExpenses'])->name('top-expenses');
            
            // گزارش‌های ارزی (Currency/FX)
            Route::get('/currency-transactions', [Admin\ReportsController::class, 'currencyTransactions'])->name('currency-transactions');
            Route::get('/fx-gain-loss', [Admin\ReportsController::class, 'fxGainLoss'])->name('fx-gain-loss');
            Route::get('/fx-rates-used', [Admin\ReportsController::class, 'fxRatesUsed'])->name('fx-rates-used');
            Route::get('/foreign-purchases', [Admin\ReportsController::class, 'foreignPurchases'])->name('foreign-purchases');
            Route::get('/currency-balances', [Admin\ReportsController::class, 'currencyBalances'])->name('currency-balances');
            
            // گزارش‌های COGS
            Route::get('/cogs-report', [Admin\ReportsController::class, 'cogsReport'])->name('cogs-report');
            Route::get('/product-profitability', [Admin\ReportsController::class, 'productProfitability'])->name('product-profitability');
            Route::get('/sales-vs-cogs', [Admin\ReportsController::class, 'salesVsCogs'])->name('sales-vs-cogs');
            Route::get('/cogs-monthly-trend', [Admin\ReportsController::class, 'cogsMonthlyTrend'])->name('cogs-monthly-trend');
            
            // گزارش‌های فروش (Sales)
            Route::get('/sales-summary', [Admin\ReportsController::class, 'salesSummary'])->name('sales-summary');
            Route::get('/sales-by-customer', [Admin\ReportsController::class, 'salesByCustomer'])->name('sales-by-customer');
            Route::get('/sales-by-product', [Admin\ReportsController::class, 'salesByProduct'])->name('sales-by-product');
            Route::get('/sales-trend', [Admin\ReportsController::class, 'salesTrend'])->name('sales-trend');
            
            // گزارش‌های تطبیق (Reconciliation)
            Route::get('/bank-reconciliation', [Admin\ReportsController::class, 'bankReconciliation'])->name('bank-reconciliation');
            Route::get('/cashbox-reconciliation', [Admin\ReportsController::class, 'cashboxReconciliation'])->name('cashbox-reconciliation');
            Route::get('/unreconciled-items', [Admin\ReportsController::class, 'unreconciledItems'])->name('unreconciled-items');
            Route::get('/reconciliation-history', [Admin\ReportsController::class, 'reconciliationHistory'])->name('reconciliation-history');
            
            // گزارش‌های تحلیلی (Analytics)
            Route::get('/cash-flow-forecast', [Admin\ReportsController::class, 'cashFlowForecast'])->name('cash-flow-forecast');
            Route::get('/financial-ratios', [Admin\ReportsController::class, 'financialRatios'])->name('financial-ratios');
            Route::get('/profitability-analysis', [Admin\ReportsController::class, 'profitabilityAnalysis'])->name('profitability-analysis');
            Route::get('/revenue-trend', [Admin\ReportsController::class, 'revenueTrend'])->name('revenue-trend');
            Route::get('/period-comparison', [Admin\ReportsController::class, 'periodComparison'])->name('period-comparison');
            
            // گزارش‌های سال مالی
            Route::get('/fiscal-year-performance', [Admin\ReportsController::class, 'fiscalYearPerformance'])->name('fiscal-year-performance');
            Route::get('/year-over-year', [Admin\ReportsController::class, 'yearOverYear'])->name('year-over-year');
            Route::get('/closing-report', [Admin\ReportsController::class, 'closingReport'])->name('closing-report');
            
            // گزارش‌های Audit
            Route::get('/audit-trail', [Admin\ReportsController::class, 'auditTrail'])->name('audit-trail');
            Route::get('/document-reversals', [Admin\ReportsController::class, 'documentReversals'])->name('document-reversals');
            Route::get('/accounting-activity-log', [Admin\ReportsController::class, 'accountingActivityLog'])->name('accounting-activity-log');
            Route::get('/discrepancies', [Admin\ReportsController::class, 'discrepancies'])->name('discrepancies');
        });
    });
