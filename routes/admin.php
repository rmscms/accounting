<?php

use Illuminate\Support\Facades\Route;
use RMS\Core\Helpers\RouteHelper;
use RMS\Accounting\Http\Controllers\Admin;

/*
|--------------------------------------------------------------------------
| Admin Routes - Accounting Package
|--------------------------------------------------------------------------
*/

Route::prefix(config('accounting.routes.admin_prefix', 'admin/accounting'))
    ->middleware(array_merge(
        config('accounting.routes.admin_middleware', ['web', 'auth:admin']),
        (array) config('accounting.routes.admin_extra_middleware', [])
    ))
    ->name('admin.accounting.')
    ->group(function () {

        // اسکریپت تاریخ واحد از داخل پکیج (بدون نیاز به publish در public)
        Route::get('/assets/accounting-date-ui.js', static function () {
            $path = dirname(__DIR__).'/resources/assets/admin/js/accounting-date-ui.js';
            if (! is_file($path)) {
                abort(404);
            }

            return response()->file($path, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'public, max-age=604800',
            ]);
        })->name('assets.accounting-date-ui');

        // نصب / ویزارد اولیه (قبل از داشبورد)
        Route::get('/install', [Admin\InstallController::class, 'show'])->name('install');
        Route::post('/install/run', [Admin\InstallController::class, 'run'])
            ->middleware('throttle:6,1')
            ->name('install.run');

        Route::get('/onboarding', [Admin\AccountingOnboardingController::class, 'show'])->name('onboarding');
        Route::post('/onboarding/run-chart-install', [Admin\AccountingOnboardingController::class, 'runChartInstall'])
            ->middleware('throttle:4,1')
            ->name('onboarding.run-chart-install');
        Route::get('/guides/opening-balance', [Admin\OpeningBalanceGuideController::class, 'show'])->name('guides.opening-balance');

        Route::get('/ajax/payment-destinations', [Admin\Ajax\PaymentDestinationsController::class, 'catalog'])
            ->middleware('throttle:90,1')
            ->name('ajax.payment-destinations');

        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/foreign-exchange', [Admin\ForeignExchangeHubController::class, 'index'])->name('foreign-exchange.index');
        Route::post('/foreign-exchange', [Admin\ForeignExchangeHubController::class, 'postConversion'])
            ->middleware('throttle:30,1')
            ->name('foreign-exchange.store');
        Route::post('/dashboard/setup-cheque-clearing-accounts', [Admin\DashboardController::class, 'setupChequeClearingAccounts'])
            ->middleware('throttle:8,1')
            ->name('dashboard.setup-cheque-clearing-accounts');
        
        // تنظیمات
        Route::get('/settings', [Admin\SettingsController::class, 'showSettings'])->name('settings.index');
        Route::put('/settings', [Admin\SettingsController::class, 'saveSettings'])->name('settings.update');
        Route::post('/settings/create-default-sales-customer', [Admin\SettingsController::class, 'createDefaultSalesCustomer'])
            ->middleware('throttle:6,1')
            ->name('settings.create-default-sales-customer');
        Route::get('/settings/sample-data', [Admin\AccountingSampleDataController::class, 'index'])->name('sample-data.index');
        Route::post('/settings/sample-data/preflight', [Admin\AccountingSampleDataController::class, 'preflight'])
            ->middleware('throttle:12,1')
            ->name('sample-data.preflight');
        Route::post('/settings/sample-data/generate', [Admin\AccountingSampleDataController::class, 'generate'])
            ->middleware('throttle:3,1')
            ->name('sample-data.generate');
        Route::post('/settings/sample-data/wipe', [Admin\AccountingSampleDataController::class, 'wipe'])
            ->middleware('throttle:2,1')
            ->name('sample-data.wipe');
        Route::get('/settings/scenario-runner', [Admin\AccountingScenarioRunnerController::class, 'index'])
            ->name('scenario-runner.index');
        Route::post('/settings/scenario-runner/preview', [Admin\AccountingScenarioRunnerController::class, 'preview'])
            ->name('scenario-runner.preview');
        Route::post('/settings/scenario-runner/run', [Admin\AccountingScenarioRunnerController::class, 'run'])
            ->name('scenario-runner.run');
        Route::post('/settings/scenario-runner/reset', [Admin\AccountingScenarioRunnerController::class, 'reset'])
            ->name('scenario-runner.reset');
        Route::get('/settings/scenario-runner/{scenarioKey}/errors', [Admin\AccountingScenarioRunnerController::class, 'errors'])
            ->name('scenario-runner.errors');

        // حساب‌ها (Chart of Accounts)
        RouteHelper::adminResource(Admin\AccountsController::class, 'accounts');
        Route::resource('accounts', Admin\AccountsController::class);
        Route::get('/accounts/tree', [Admin\AccountsController::class, 'tree'])->name('accounts.tree');
        Route::get('/accounts/{id}/statement', [Admin\AccountsController::class, 'statement'])->name('accounts.statement');

        // دفتر روزنامه — فهرست خطوط به ترتیب زمان؛ گزارش دفتر کل جداست (reports.general-ledger)
        Route::get('/ledger', [Admin\LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/{id}', [Admin\LedgerController::class, 'show'])->name('ledger.show');
        Route::get('/ledger/export', [Admin\LedgerController::class, 'exportLedger'])->name('ledger.export');

        // اسناد حسابداری
        RouteHelper::adminResource(Admin\DocumentsController::class, 'documents');
        Route::resource('documents', Admin\DocumentsController::class);
        Route::post('/documents/{id}/post', [Admin\DocumentsController::class, 'post'])->name('documents.post');
        Route::post('/documents/{id}/reverse', [Admin\DocumentsController::class, 'reverse'])->name('documents.reverse');

        // سال‌های مالی
        RouteHelper::adminResource(Admin\FiscalYearsController::class, 'fiscal_years');
        Route::get('/fiscal_years/{fiscal_year}/close-wizard', [Admin\FiscalYearCloseWizardController::class, 'show'])
            ->name('fiscal_years.close_wizard');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard', [Admin\FiscalYearCloseWizardController::class, 'execute'])
            ->middleware('throttle:12,1')
            ->name('fiscal_years.close_wizard.execute');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard/precheck', [Admin\FiscalYearCloseWizardController::class, 'precheck'])
            ->middleware('throttle:30,1')
            ->name('fiscal_years.close_wizard.precheck');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard/preview', [Admin\FiscalYearCloseWizardController::class, 'preview'])
            ->middleware('throttle:30,1')
            ->name('fiscal_years.close_wizard.preview');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard/execute-step', [Admin\FiscalYearCloseWizardController::class, 'executeStep'])
            ->middleware('throttle:12,1')
            ->name('fiscal_years.close_wizard.execute_step');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard/postcheck', [Admin\FiscalYearCloseWizardController::class, 'postcheck'])
            ->middleware('throttle:30,1')
            ->name('fiscal_years.close_wizard.postcheck');
        Route::post('/fiscal_years/{fiscal_year}/close-wizard/open-next', [Admin\FiscalYearCloseWizardController::class, 'openNext'])
            ->middleware('throttle:12,1')
            ->name('fiscal_years.close_wizard.open_next');
        Route::post('/fiscal_years/{id}/close', [Admin\FiscalYearsController::class, 'close'])->name('fiscal_years.close');
        Route::resource('fiscal_years', Admin\FiscalYearsController::class);

        // ارزها
        RouteHelper::adminResource(Admin\CurrenciesController::class, 'currencies');
        Route::get('/currencies/reference-rates', [Admin\CurrenciesController::class, 'referenceRates'])
            ->name('currencies.reference-rates');
        Route::post('/currencies/reference-rates', [Admin\CurrenciesController::class, 'storeReferenceRate'])
            ->middleware('throttle:30,1')
            ->name('currencies.reference-rates.store');
        Route::post('/currencies/recalculate-reference', [Admin\CurrenciesController::class, 'recalculateReferenceSnapshots'])
            ->middleware('throttle:12,1')
            ->name('currencies.recalculate-reference');
        Route::resource('currencies', Admin\CurrenciesController::class);
      
        // مشتریان
        RouteHelper::adminResource(Admin\CustomersController::class, 'customers');
        Route::resource('customers', Admin\CustomersController::class);

        // اعتبار برگشتی (Credit Notes)
        RouteHelper::adminResource(Admin\CreditNotesController::class, 'credit-notes');
        Route::get('/credit-notes/{customer_invoice}/reference-invoice-preview', [Admin\CreditNotesController::class, 'referenceInvoicePreview'])
            ->middleware('throttle:60,1')
            ->name('credit-notes.reference-invoice-preview');
        Route::resource('credit-notes', Admin\CreditNotesController::class);
        Route::post('/credit-notes/{id}/issue', [Admin\CreditNotesController::class, 'issue'])->name('credit-notes.issue');
        Route::post('/credit-notes/{id}/apply', [Admin\CreditNotesController::class, 'apply'])->name('credit-notes.apply');

        // بازگشت وجه به مشتری
        RouteHelper::adminResource(Admin\CustomerRefundsController::class, 'customer-refunds');
        Route::resource('customer-refunds', Admin\CustomerRefundsController::class);

        // پیش دریافت از مشتری
        RouteHelper::adminResource(Admin\CustomerAdvancesController::class, 'customer-advances');
        Route::resource('customer-advances', Admin\CustomerAdvancesController::class);
        Route::post('/customer-advances/{id}/apply', [Admin\CustomerAdvancesController::class, 'apply'])->name('customer-advances.apply');

        // بانک‌ها
        Route::get('/banks/search-accounts', [Admin\BanksController::class, 'searchAssetAccounts'])
            ->middleware('throttle:90,1')
            ->name('banks.search-accounts');
        Route::get('/banks/{bank}/statement', [Admin\BanksController::class, 'show'])->name('banks.statement');
        RouteHelper::adminResource(Admin\BanksController::class, 'banks');
        Route::resource('banks', Admin\BanksController::class);

        // صندوق‌ها
        Route::get('/cashboxes/{cashbox}/statement', [Admin\CashBoxesController::class, 'show'])->name('cashboxes.statement');
        RouteHelper::adminResource(Admin\CashBoxesController::class, 'cashboxes');
        Route::resource('cashboxes', Admin\CashBoxesController::class);

        // کیف‌پول‌ها
        RouteHelper::adminResource(Admin\WalletsController::class, 'wallets');
        Route::resource('wallets', Admin\WalletsController::class);

        // ترمینال‌های POS
        RouteHelper::adminResource(Admin\POSTerminalsController::class, 'pos-terminals');
        Route::resource('pos-terminals', Admin\POSTerminalsController::class);

        // روش‌های پرداخت
        RouteHelper::adminResource(Admin\PaymentMethodsController::class, 'payment-methods');
        Route::resource('payment-methods', Admin\PaymentMethodsController::class);

        // چک‌ها
        RouteHelper::adminResource(Admin\ChequesController::class, 'cheques');
        RouteHelper::adminResource(Admin\ChequebooksController::class, 'chequebooks');
        Route::resource('cheques', Admin\ChequesController::class);
        Route::resource('chequebooks', Admin\ChequebooksController::class);
        Route::post('/cheques/{id}/cash', [Admin\ChequesController::class, 'cash'])->name('cheques.cash');
        Route::post('/cheques/{id}/bounce', [Admin\ChequesController::class, 'bounce'])->name('cheques.bounce');

        // فاکتورهای مشتریان (مسیرهای Ajax قبل از resource)
        Route::get('/customer-invoices/ajax/customers', [Admin\CustomerInvoicesController::class, 'searchCustomers'])
            ->middleware('throttle:90,1')
            ->name('customer-invoices.search-customers');
        Route::post('/customer-invoices/ajax/customers', [Admin\CustomerInvoicesController::class, 'quickCreateCustomer'])
            ->middleware('throttle:45,1')
            ->name('customer-invoices.quick-create-customer');
        Route::get('/customer-invoices/ajax/check-invoice-number', [Admin\CustomerInvoicesController::class, 'checkInvoiceNumber'])
            ->middleware('throttle:90,1')
            ->name('customer-invoices.check-invoice-number');
        Route::get('/customer-invoices/{customer_invoice}/items-fragment', [Admin\CustomerInvoicesController::class, 'itemsFragment'])
            ->middleware('throttle:60,1')
            ->name('customer-invoices.items-fragment');
        Route::post('/customer-invoices/{customer_invoice}/items', [Admin\CustomerInvoiceItemsController::class, 'itemsStore'])
            ->middleware('throttle:60,1')
            ->name('customer-invoices.items.store');
        Route::put('/customer-invoices/{customer_invoice}/items/{item}', [Admin\CustomerInvoiceItemsController::class, 'itemsUpdate'])
            ->middleware('throttle:60,1')
            ->name('customer-invoices.items.update');
        Route::delete('/customer-invoices/{customer_invoice}/items/{item}', [Admin\CustomerInvoiceItemsController::class, 'itemsDestroy'])
            ->middleware('throttle:60,1')
            ->name('customer-invoices.items.destroy');
        Route::post('/customer-invoices/{customer_invoice}/post-document', [Admin\CustomerInvoicesController::class, 'postAccountingDocument'])
            ->middleware('throttle:30,1')
            ->name('customer-invoices.post-document');
        Route::post('/customer-invoices/{customer_invoice}/reverse-and-replace', [Admin\CustomerInvoicesController::class, 'reverseAndCreateReplacement'])
            ->middleware('throttle:20,1')
            ->name('customer-invoices.reverse-and-replace');
        Route::post('/customer-invoices/{customer_invoice}/adjustment', [Admin\CustomerInvoicesController::class, 'createAdjustment'])
            ->middleware('throttle:30,1')
            ->name('customer-invoices.adjustment');

        RouteHelper::adminResource(Admin\CustomerInvoicesController::class, 'customer-invoices');
        Route::resource('customer-invoices', Admin\CustomerInvoicesController::class);

        // دریافت‌های مشتریان (Ajax قبل از resource)
        Route::get('/customer-payments/ajax/check-payment-number', [Admin\CustomerPaymentsController::class, 'checkPaymentNumber'])
            ->middleware('throttle:90,1')
            ->name('customer-payments.check-payment-number');

        // دریافت‌های مشتریان
        RouteHelper::adminResource(Admin\CustomerPaymentsController::class, 'customer-payments');
        Route::resource('customer-payments', Admin\CustomerPaymentsController::class);

        // تامین‌کنندگان
        Route::get('/suppliers/ajax/parties', [Admin\SuppliersController::class, 'searchParties'])
            ->middleware('throttle:90,1')
            ->name('suppliers.search-parties');
        Route::get('/suppliers/ajax/customers', [Admin\SuppliersController::class, 'searchCustomersForSupplier'])
            ->middleware('throttle:90,1')
            ->name('suppliers.search-customers');

        RouteHelper::adminResource(Admin\SuppliersController::class, 'suppliers');
        Route::resource('suppliers', Admin\SuppliersController::class);

        // یادداشت بدهکار (Debit Notes)
        RouteHelper::adminResource(Admin\DebitNotesController::class, 'debit-notes');
        Route::resource('debit-notes', Admin\DebitNotesController::class);
        Route::post('/debit-notes/{id}/issue', [Admin\DebitNotesController::class, 'issue'])->name('debit-notes.issue');
        Route::post('/debit-notes/{id}/apply', [Admin\DebitNotesController::class, 'apply'])->name('debit-notes.apply');

        // دریافت بازگشت از تامین‌کننده
        RouteHelper::adminResource(Admin\SupplierRefundsController::class, 'supplier-refunds');
        Route::resource('supplier-refunds', Admin\SupplierRefundsController::class);

        // پیش پرداخت به تامین‌کننده
        RouteHelper::adminResource(Admin\SupplierAdvancesController::class, 'supplier-advances');
        Route::resource('supplier-advances', Admin\SupplierAdvancesController::class);
        Route::post('/supplier-advances/{id}/apply', [Admin\SupplierAdvancesController::class, 'apply'])->name('supplier-advances.apply');

        // داشبورد راهنمای خرید (قبل از resourceها)
        Route::get('/purchases/dashboard', [Admin\PurchasesDashboardController::class, 'index'])->name('purchases.dashboard');

        // سفارش خرید — اقلام (قبل از resource)
        Route::get('/purchase-orders/{purchase_order}/items-fragment', [Admin\PurchaseOrdersController::class, 'itemsFragment'])
            ->middleware('throttle:60,1')
            ->name('purchase-orders.items-fragment');
        Route::post('/purchase-orders/{purchase_order}/items', [Admin\PurchaseOrderItemsController::class, 'itemsStore'])
            ->middleware('throttle:60,1')
            ->name('purchase-orders.items.store');
        Route::put('/purchase-orders/{purchase_order}/items/{item}', [Admin\PurchaseOrderItemsController::class, 'itemsUpdate'])
            ->middleware('throttle:60,1')
            ->name('purchase-orders.items.update');
        Route::delete('/purchase-orders/{purchase_order}/items/{item}', [Admin\PurchaseOrderItemsController::class, 'itemsDestroy'])
            ->middleware('throttle:60,1')
            ->name('purchase-orders.items.destroy');
        Route::get('/purchase-orders/ajax/check-po-number', [Admin\PurchaseOrdersController::class, 'checkPoNumber'])
            ->middleware('throttle:90,1')
            ->name('purchase-orders.check-po-number');
        Route::get('/purchase-orders/{purchase_order}/warehouse-receipt.pdf', [Admin\PurchaseOrdersController::class, 'warehouseReceiptPdf'])
            ->middleware('throttle:30,1')
            ->name('purchase-orders.warehouse-receipt-pdf');
        Route::post('/purchase-orders/{purchase_order}/supplier-invoices/from-purchase-order', [Admin\PurchaseOrdersController::class, 'createSupplierInvoiceFromPurchaseOrder'])
            ->middleware('throttle:20,1')
            ->name('purchase-orders.supplier-invoices.from-purchase-order');

        // سفارش خرید
        RouteHelper::adminResource(Admin\PurchaseOrdersController::class, 'purchase-orders');
        Route::resource('purchase-orders', Admin\PurchaseOrdersController::class);
        Route::post('/purchase-orders/{id}/confirm', [Admin\PurchaseOrdersController::class, 'confirm'])->name('purchase-orders.confirm');

        // فاکتورهای خرید (مسیرهای ثابت قبل از resource)
        Route::get('/supplier-invoices/ajax/suppliers', [Admin\SupplierInvoicesController::class, 'searchSuppliers'])
            ->middleware('throttle:90,1')
            ->name('supplier-invoices.search-suppliers');
        Route::get('/supplier-invoices/ajax/invoices-for-supplier', [Admin\SupplierInvoicesController::class, 'searchInvoicesForSupplier'])
            ->middleware('throttle:90,1')
            ->name('supplier-invoices.search-invoices');
        Route::get('/supplier-invoices/ajax/check-invoice-number', [Admin\SupplierInvoicesController::class, 'checkInvoiceNumber'])
            ->middleware('throttle:90,1')
            ->name('supplier-invoices.check-invoice-number');
        Route::get('/supplier-invoices/{supplier_invoice}/items-fragment', [Admin\SupplierInvoicesController::class, 'itemsFragment'])
            ->middleware('throttle:60,1')
            ->name('supplier-invoices.items-fragment');
        Route::get('/supplier-invoices/{supplier_invoice}/debit-note-reference-preview', [Admin\SupplierInvoicesController::class, 'debitNoteReferencePreview'])
            ->middleware('throttle:60,1')
            ->name('supplier-invoices.debit-note-reference-preview');
        Route::post('/supplier-invoices/{supplier_invoice}/items', [Admin\SupplierInvoiceItemsController::class, 'itemsStore'])
            ->middleware('throttle:60,1')
            ->name('supplier-invoices.items.store');
        Route::put('/supplier-invoices/{supplier_invoice}/items/{item}', [Admin\SupplierInvoiceItemsController::class, 'itemsUpdate'])
            ->middleware('throttle:60,1')
            ->name('supplier-invoices.items.update');
        Route::delete('/supplier-invoices/{supplier_invoice}/items/{item}', [Admin\SupplierInvoiceItemsController::class, 'itemsDestroy'])
            ->middleware('throttle:60,1')
            ->name('supplier-invoices.items.destroy');
        Route::post('/supplier-invoices/{supplier_invoice}/post-document', [Admin\SupplierInvoicesController::class, 'postAccountingDocument'])
            ->middleware('throttle:30,1')
            ->name('supplier-invoices.post-document');
        Route::post('/supplier-invoices/{supplier_invoice}/reverse-and-replace', [Admin\SupplierInvoicesController::class, 'reverseAndCreateReplacement'])
            ->middleware('throttle:20,1')
            ->name('supplier-invoices.reverse-and-replace');
        Route::post('/supplier-invoices/{supplier_invoice}/adjustment', [Admin\SupplierInvoicesController::class, 'createAdjustment'])
            ->middleware('throttle:30,1')
            ->name('supplier-invoices.adjustment');

        RouteHelper::adminResource(Admin\SupplierInvoicesController::class, 'supplier-invoices');
        Route::resource('supplier-invoices', Admin\SupplierInvoicesController::class);

        // پرداخت‌های تامین‌کنندگان (Ajax قبل از resource)
        Route::get('/supplier-payments/ajax/check-payment-number', [Admin\SupplierPaymentsController::class, 'checkPaymentNumber'])
            ->middleware('throttle:90,1')
            ->name('supplier-payments.check-payment-number');
        Route::post('/supplier-payments/{supplier_payment}/void', [Admin\SupplierPaymentsController::class, 'voidPayment'])
            ->middleware('throttle:12,1')
            ->name('supplier-payments.void');

        RouteHelper::adminResource(Admin\SupplierPaymentsController::class, 'supplier-payments');
        Route::resource('supplier-payments', Admin\SupplierPaymentsController::class);

        // دسته‌بندی هزینه‌ها
        Route::get('/expense-categories/check-code', [Admin\ExpenseCategoriesController::class, 'checkCode'])
            ->middleware('throttle:60,1')
            ->name('expense-categories.check-code');
        RouteHelper::adminResource(Admin\ExpenseCategoriesController::class, 'expense-categories');
        Route::resource('expense-categories', Admin\ExpenseCategoriesController::class);

        // پیوست‌های فایلی خصوصی (رسید / PDF — نه سند دفترکل)
        Route::post('/attachments', [Admin\AccountingAttachmentsController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('attachments.store');
        Route::get('/attachments/{uuid}/download', [Admin\AccountingAttachmentsController::class, 'download'])
            ->middleware('throttle:120,1')
            ->name('attachments.download');

        // هزینه‌ها
        RouteHelper::adminResource(Admin\ExpensesController::class, 'expenses');
        Route::resource('expenses', Admin\ExpensesController::class);
        Route::post('/expenses/{id}/approve', [Admin\ExpensesController::class, 'approve'])->name('expenses.approve');

        // نرخ مالیات
        RouteHelper::adminResource(Admin\TaxRatesController::class, 'tax-rates');
        Route::resource('tax-rates', Admin\TaxRatesController::class);

        // تطبیق پرداخت‌ها
        RouteHelper::adminResource(Admin\ReconciliationsController::class, 'reconciliations');
        Route::resource('reconciliations', Admin\ReconciliationsController::class);
        Route::post('/reconciliations/{id}/confirm', [Admin\ReconciliationsController::class, 'confirm'])->name('reconciliations.confirm');
        Route::post('/reconciliations/auto', [Admin\ReconciliationsController::class, 'autoReconcile'])->name('reconciliations.auto');

        // Workspace تطبیق بانک (نسخه پیشرفته AJAX)
        Route::get('/bank-reconciliation/workspace', [Admin\BankReconciliationWorkspaceController::class, 'workspace'])
            ->name('bank-reconciliation.workspace');
        Route::post('/bank-reconciliation/session', [Admin\BankReconciliationWorkspaceController::class, 'openSession'])
            ->name('bank-reconciliation.session');
        Route::get('/bank-reconciliation/session/{session}', [Admin\BankReconciliationWorkspaceController::class, 'loadSession'])
            ->name('bank-reconciliation.session.load');
        Route::delete('/bank-reconciliation/session/{session}', [Admin\BankReconciliationWorkspaceController::class, 'deleteSession'])
            ->name('bank-reconciliation.session.delete');
        Route::post('/bank-reconciliation/{session}/items', [Admin\BankReconciliationWorkspaceController::class, 'addItem'])
            ->name('bank-reconciliation.items.add');
        Route::delete('/bank-reconciliation/{session}/items/{itemId}', [Admin\BankReconciliationWorkspaceController::class, 'removeItem'])
            ->name('bank-reconciliation.items.delete');
        Route::post('/bank-reconciliation/{session}/validate', [Admin\BankReconciliationWorkspaceController::class, 'validateSession'])
            ->name('bank-reconciliation.validate');
        Route::post('/bank-reconciliation/{session}/finalize', [Admin\BankReconciliationWorkspaceController::class, 'finalizeSession'])
            ->name('bank-reconciliation.finalize');
        Route::get('/bank-reconciliation/{session}/candidates/outstanding-cheques', [Admin\BankReconciliationWorkspaceController::class, 'outstandingCheques'])
            ->name('bank-reconciliation.candidates.outstanding-cheques');
        Route::get('/bank-reconciliation/{session}/candidates/deposits-in-transit', [Admin\BankReconciliationWorkspaceController::class, 'depositsInTransit'])
            ->name('bank-reconciliation.candidates.deposits-in-transit');
        Route::post('/bank-reconciliation/{session}/attachments', [Admin\BankReconciliationWorkspaceController::class, 'uploadAttachment'])
            ->name('bank-reconciliation.attachments.upload');

        // تعهدات (Accruals)
        RouteHelper::adminResource(Admin\AccrualsController::class, 'accruals');
        Route::resource('accruals', Admin\AccrualsController::class);
        Route::post('/accruals/{id}/reverse', [Admin\AccrualsController::class, 'reverse'])->name('accruals.reverse');

        // مطالبات مشکوک (Bad Debt)
        Route::get('bad-debt/search-customers', [Admin\BadDebtController::class, 'searchCustomers'])->name('bad-debt.search-customers');
        RouteHelper::adminResource(Admin\BadDebtController::class, 'bad-debt');
        Route::resource('bad-debt', Admin\BadDebtController::class);
        Route::post('/bad-debt/writeoff', [Admin\BadDebtController::class, 'writeoff'])->name('bad-debt.writeoff');

        // دسته‌بندی دارایی‌های ثابت
        RouteHelper::adminResource(Admin\FixedAssetCategoriesController::class, 'fixed-asset-categories');
        Route::resource('fixed-asset-categories', Admin\FixedAssetCategoriesController::class);

        // دارایی‌های ثابت
        RouteHelper::adminResource(Admin\FixedAssetsController::class, 'fixed-assets');
        Route::resource('fixed-assets', Admin\FixedAssetsController::class);
        Route::post('/fixed-assets/{id}/generate-schedule', [Admin\FixedAssetsController::class, 'generateSchedule'])->name('fixed-assets.generate-schedule');
        Route::post('/fixed-assets/{id}/record-depreciation', [Admin\FixedAssetsController::class, 'recordDepreciation'])->name('fixed-assets.record-depreciation');
        Route::post('/fixed-assets/{id}/dispose', [Admin\FixedAssetsController::class, 'dispose'])->name('fixed-assets.dispose');

        // انتقال بین بانکی
        RouteHelper::adminResource(Admin\BankTransfersController::class, 'bank-transfers');
        Route::resource('bank-transfers', Admin\BankTransfersController::class);
        Route::post('/bank-transfers/{id}/process', [Admin\BankTransfersController::class, 'process'])->name('bank-transfers.process');
        Route::post('/bank-transfers/{id}/complete', [Admin\BankTransfersController::class, 'complete'])->name('bank-transfers.complete');
        Route::post('/bank-transfers/{id}/cancel', [Admin\BankTransfersController::class, 'cancel'])->name('bank-transfers.cancel');
        Route::get('/treasury/sync-balances', [Admin\TreasuryBalanceSyncController::class, 'index'])->name('treasury-sync.index');
        Route::post('/treasury/sync-balances/{type}/{id}', [Admin\TreasuryBalanceSyncController::class, 'syncOne'])->name('treasury-sync.sync-one');

        // تراکنش‌های بانکی (کارمزد/سود)
        RouteHelper::adminResource(Admin\BankTransactionsController::class, 'bank-transactions');
        Route::resource('bank-transactions', Admin\BankTransactionsController::class);
        Route::post('/bank-transactions/{id}/post', [Admin\BankTransactionsController::class, 'post'])->name('bank-transactions.post');

        // اسناد دستی
        RouteHelper::adminResource(Admin\ManualJournalsController::class, 'manual-journals');
        Route::resource('manual-journals', Admin\ManualJournalsController::class);
        Route::post('/manual-journals/{id}/post', [Admin\ManualJournalsController::class, 'post'])->name('manual-journals.post');
        Route::post('/manual-journals/{id}/lines', [Admin\ManualJournalsController::class, 'storeLine'])->name('manual-journals.lines.store');
        Route::put('/manual-journals/{id}/lines/{line}', [Admin\ManualJournalsController::class, 'updateLine'])->name('manual-journals.lines.update');
        Route::delete('/manual-journals/{id}/lines/{line}', [Admin\ManualJournalsController::class, 'destroyLine'])->name('manual-journals.lines.destroy');
        Route::post('/manual-journals/{id}/reverse', [Admin\ManualJournalsController::class, 'reverse'])->name('manual-journals.reverse');
        Route::post('/manual-journals/{id}/duplicate', [Admin\ManualJournalsController::class, 'duplicate'])->name('manual-journals.duplicate');

        // سهامداران، کارمندان، برداشت و افزایش سرمایه
        Route::get('/shareholders', [Admin\ShareholdersController::class, 'shareholdersIndex'])->name('shareholders.index');
        Route::get('/shareholders/create', [Admin\ShareholdersController::class, 'shareholdersCreate'])->name('shareholders.create');
        Route::post('/shareholders', [Admin\ShareholdersController::class, 'shareholdersStore'])->name('shareholders.store');
        Route::get('/shareholders/{shareholder}/edit', [Admin\ShareholdersController::class, 'shareholdersEdit'])->name('shareholders.edit');
        Route::put('/shareholders/{shareholder}', [Admin\ShareholdersController::class, 'shareholdersUpdate'])->name('shareholders.update');
        Route::delete('/shareholders/{shareholder}', [Admin\ShareholdersController::class, 'shareholdersDestroy'])->name('shareholders.destroy');

        Route::get('/shareholders-equity-overview', [Admin\ShareholderEquityOverviewController::class, 'index'])
            ->name('shareholders-equity-overview.index');

        Route::get('/employees', [Admin\EmployeesController::class, 'employeesIndex'])->name('employees.index');
        Route::get('/employees/create', [Admin\EmployeesController::class, 'employeesCreate'])->name('employees.create');
        Route::post('/employees', [Admin\EmployeesController::class, 'employeesStore'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [Admin\EmployeesController::class, 'employeesEdit'])->name('employees.edit');
        Route::put('/employees/{employee}', [Admin\EmployeesController::class, 'employeesUpdate'])->name('employees.update');
        Route::delete('/employees/{employee}', [Admin\EmployeesController::class, 'employeesDestroy'])->name('employees.destroy');
        Route::get('/payroll-runs', [Admin\PayrollRunsController::class, 'index'])->name('payroll-runs.index');
        Route::get('/payroll-runs/create', [Admin\PayrollRunsController::class, 'create'])->name('payroll-runs.create');
        Route::post('/payroll-runs', [Admin\PayrollRunsController::class, 'payrollRunsStore'])->name('payroll-runs.store');
        Route::get('/payroll-runs/{run}', [Admin\PayrollRunsController::class, 'show'])->name('payroll-runs.show');
        Route::get('/payroll-runs/{run}/edit', [Admin\PayrollRunsController::class, 'edit'])->name('payroll-runs.edit');
        Route::put('/payroll-runs/{run}', [Admin\PayrollRunsController::class, 'payrollRunsUpdate'])->name('payroll-runs.update');
        Route::post('/payroll-runs/{run}/post-accrual', [Admin\PayrollRunsController::class, 'postAccrual'])->name('payroll-runs.accrual');
        Route::post('/payroll-runs/{run}/reverse-accrual', [Admin\PayrollRunsController::class, 'reverseAccrual'])->name('payroll-runs.accrual.reverse');
        Route::post('/payroll-runs/{run}/post-net-payment', [Admin\PayrollRunsController::class, 'postNetPayment'])->name('payroll-runs.net-payment');
        Route::post('/payroll-runs/{run}/reverse-net-payment', [Admin\PayrollRunsController::class, 'reverseNetPayment'])->name('payroll-runs.net-payment.reverse');
        Route::post('/payroll-runs/{run}/post-insurance-remittance', [Admin\PayrollRunsController::class, 'postInsuranceRemittance'])->name('payroll-runs.insurance-remittance');
        Route::post('/payroll-runs/{run}/reverse-insurance-remittance', [Admin\PayrollRunsController::class, 'reverseInsuranceRemittance'])->name('payroll-runs.insurance-remittance.reverse');
        Route::post('/payroll-runs/{run}/post-tax-remittance', [Admin\PayrollRunsController::class, 'postTaxRemittance'])->name('payroll-runs.tax-remittance');
        Route::post('/payroll-runs/{run}/reverse-tax-remittance', [Admin\PayrollRunsController::class, 'reverseTaxRemittance'])->name('payroll-runs.tax-remittance.reverse');
        Route::post('/payroll-runs/{run}/post-loan-settlement', [Admin\PayrollRunsController::class, 'postLoanSettlement'])->name('payroll-runs.loan-settlement');
        Route::post('/payroll-runs/{run}/reverse-loan-settlement', [Admin\PayrollRunsController::class, 'reverseLoanSettlement'])->name('payroll-runs.loan-settlement.reverse');
        Route::post('/payroll-runs/{run}/post-seniority-settlement', [Admin\PayrollRunsController::class, 'postSenioritySettlement'])->name('payroll-runs.seniority-settlement');
        Route::post('/payroll-runs/{run}/reverse-seniority-settlement', [Admin\PayrollRunsController::class, 'reverseSenioritySettlement'])->name('payroll-runs.seniority-settlement.reverse');
        Route::get('/payroll-runs/{run}/payslips/{line}/print', [Admin\PayrollRunsController::class, 'printPayslip'])->name('payroll-runs.payslips.print');
        Route::get('/attendance-worklogs', [Admin\AttendanceWorklogsController::class, 'index'])->name('attendance-worklogs.index');
        Route::post('/attendance-worklogs/open-period', [Admin\AttendanceWorklogsController::class, 'openPeriod'])->name('attendance-worklogs.open-period');
        Route::get('/attendance-worklogs/{period}', [Admin\AttendanceWorklogsController::class, 'show'])->name('attendance-worklogs.show');
        Route::post('/attendance-worklogs/daily-upsert', [Admin\AttendanceWorklogsController::class, 'upsertDaily'])->name('attendance-worklogs.daily-upsert');
        Route::post('/attendance-worklogs/import-csv', [Admin\AttendanceWorklogsController::class, 'importCsv'])->name('attendance-worklogs.import-csv');
        Route::post('/attendance-worklogs/import-device', [Admin\AttendanceWorklogsController::class, 'importDevice'])->name('attendance-worklogs.import-device');
        Route::post('/attendance-worklogs/{period}/submit', [Admin\AttendanceWorklogsController::class, 'submit'])->name('attendance-worklogs.submit');
        Route::post('/attendance-worklogs/{period}/supervisor-approve', [Admin\AttendanceWorklogsController::class, 'supervisorApprove'])->name('attendance-worklogs.supervisor-approve');
        Route::post('/attendance-worklogs/{period}/hr-approve', [Admin\AttendanceWorklogsController::class, 'hrApprove'])->name('attendance-worklogs.hr-approve');
        Route::post('/attendance-worklogs/{period}/lock', [Admin\AttendanceWorklogsController::class, 'lock'])->name('attendance-worklogs.lock');
        Route::post('/attendance-worklogs/{period}/unlock', [Admin\AttendanceWorklogsController::class, 'unlock'])->name('attendance-worklogs.unlock');

        Route::get('/employee-loans', [Admin\EmployeeLoansController::class, 'index'])->name('employee-loans.index');
        Route::get('/employee-loans/create', [Admin\EmployeeLoansController::class, 'create'])->name('employee-loans.create');
        Route::post('/employee-loans', [Admin\EmployeeLoansController::class, 'employeeLoansStore'])->name('employee-loans.store');
        Route::get('/employee-loans/{loan}', [Admin\EmployeeLoansController::class, 'show'])->name('employee-loans.show');
        Route::get('/employee-loans/{loan}/edit', [Admin\EmployeeLoansController::class, 'edit'])->name('employee-loans.edit');
        Route::put('/employee-loans/{loan}', [Admin\EmployeeLoansController::class, 'employeeLoansUpdate'])->name('employee-loans.update');
        Route::post('/employee-loans/{loan}/cancel', [Admin\EmployeeLoansController::class, 'cancel'])->name('employee-loans.cancel');
        Route::get('/employee-loans/{loan}/installments-print', [Admin\EmployeeLoansController::class, 'printInstallments'])->name('employee-loans.installments-print');
        Route::post('/employee-loans/{loan}/manual-payment', [Admin\EmployeeLoansController::class, 'postManualPayment'])->name('employee-loans.manual-payment');
        Route::get('/employee-contracts', [Admin\EmployeeContractsController::class, 'index'])->name('employee-contracts.index');
        Route::get('/employee-contracts/create', [Admin\EmployeeContractsController::class, 'create'])->name('employee-contracts.create');
        Route::post('/employee-contracts', [Admin\EmployeeContractsController::class, 'employeeContractsStore'])->name('employee-contracts.store');
        Route::get('/employee-contracts/{contract}', [Admin\EmployeeContractsController::class, 'show'])->name('employee-contracts.show');
        Route::get('/employee-contracts/{contract}/edit', [Admin\EmployeeContractsController::class, 'edit'])->name('employee-contracts.edit');
        Route::put('/employee-contracts/{contract}', [Admin\EmployeeContractsController::class, 'employeeContractsUpdate'])->name('employee-contracts.update');
        Route::post('/employee-contracts/{contract}/end', [Admin\EmployeeContractsController::class, 'end'])->name('employee-contracts.end');
        Route::post('/employee-contracts/{contract}/cancel', [Admin\EmployeeContractsController::class, 'cancel'])->name('employee-contracts.cancel');
        Route::get('/employee-contracts/{contract}/print', [Admin\EmployeeContractsController::class, 'printSummary'])->name('employee-contracts.print');
        Route::get('/shareholder-withdrawals', [Admin\ShareholderWithdrawalsController::class, 'index'])->name('shareholder-withdrawals.index');
        Route::get('/shareholder-withdrawals/create', [Admin\ShareholderWithdrawalsController::class, 'create'])->name('shareholder-withdrawals.create');
        Route::post('/shareholder-withdrawals', [Admin\ShareholderWithdrawalsController::class, 'recordWithdrawal'])
            ->middleware('throttle:30,1')
            ->name('shareholder-withdrawals.store');
        Route::post('/shareholder-withdrawals/{withdrawal}/post', [Admin\ShareholderWithdrawalsController::class, 'postDraft'])
            ->middleware('throttle:30,1')
            ->name('shareholder-withdrawals.post');
        Route::get('/shareholder-capital-contributions', [Admin\ShareholderCapitalContributionsController::class, 'index'])->name('shareholder-capital-contributions.index');
        Route::get('/shareholder-capital-contributions/create', [Admin\ShareholderCapitalContributionsController::class, 'create'])->name('shareholder-capital-contributions.create');
        Route::post('/shareholder-capital-contributions', [Admin\ShareholderCapitalContributionsController::class, 'recordContribution'])
            ->middleware('throttle:30,1')
            ->name('shareholder-capital-contributions.store');

        // بیمهٔ تأمین (ثبت سند تشخیص سهم کارفرما و پرداخت)
        Route::get('/payroll-insurance', [Admin\PayrollInsuranceController::class, 'index'])->name('payroll-insurance.index');
        Route::post('/payroll-insurance/accrual', [Admin\PayrollInsuranceController::class, 'postAccrual'])
            ->middleware('throttle:20,1')
            ->name('payroll-insurance.accrual');
        Route::post('/payroll-insurance/payment', [Admin\PayrollInsuranceController::class, 'postPayment'])
            ->middleware('throttle:20,1')
            ->name('payroll-insurance.payment');
        Route::post('/payroll-insurance/settlements/{settlement}/close', [Admin\PayrollInsuranceController::class, 'closeSettlement'])
            ->middleware('throttle:20,1')
            ->name('payroll-insurance.settlements.close');

        // تعدیل موجودی کالا
        RouteHelper::adminResource(Admin\InventoryAdjustmentsController::class, 'inventory-adjustments');
        Route::resource('inventory-adjustments', Admin\InventoryAdjustmentsController::class);
        Route::post('/inventory-adjustments/{id}/approve', [Admin\InventoryAdjustmentsController::class, 'approve'])->name('inventory-adjustments.approve');
        Route::post('/inventory-adjustments/{id}/post', [Admin\InventoryAdjustmentsController::class, 'post'])->name('inventory-adjustments.post');
        Route::post('/inventory-adjustments/{id}/reverse', [Admin\InventoryAdjustmentsController::class, 'reverse'])->name('inventory-adjustments.reverse');

        // گزارش‌ها
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [Admin\ReportsController::class, 'index'])->name('index');
            
            // گزارش‌های مالی اصلی (Core Financial)
            Route::get('/general-ledger', [Admin\ReportsController::class, 'generalLedger'])->name('general-ledger');
            Route::get('/general-ledger/branch', [Admin\ReportsController::class, 'generalLedgerBranch'])->name('general-ledger-branch');
            Route::get('/subsidiary-ledger', [Admin\ReportsController::class, 'subsidiaryLedger'])->name('subsidiary-ledger');
            Route::get('/trial-balance', [Admin\ReportsController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/balance-sheet', [Admin\ReportsController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/income-statement', [Admin\ReportsController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/profit-loss', [Admin\ReportsController::class, 'profitLoss'])->name('profit-loss');
            Route::get('/cash-flow', [Admin\ReportsController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/employee-loan-balances', [Admin\ReportsController::class, 'employeeLoanBalances'])->name('employee-loan-balances');
            Route::get('/employee-loan-installments-due', [Admin\ReportsController::class, 'employeeLoanInstallmentsDue'])->name('employee-loan-installments-due');
            Route::get('/employee-contracts', [Admin\ReportsController::class, 'employeeContracts'])->name('employee-contracts');
            Route::get('/attendance-monthly-summary', [Admin\ReportsController::class, 'attendanceMonthlySummary'])->name('attendance-monthly-summary');
            Route::get('/attendance-overtime-detail', [Admin\ReportsController::class, 'attendanceOvertimeDetail'])->name('attendance-overtime-detail');
            Route::get('/attendance-leave-absence', [Admin\ReportsController::class, 'attendanceLeaveAbsence'])->name('attendance-leave-absence');
            Route::get('/attendance-termination-settlement', [Admin\ReportsController::class, 'attendanceTerminationSettlement'])->name('attendance-termination-settlement');
            Route::get('/attendance-payroll-reconciliation', [Admin\ReportsController::class, 'attendancePayrollReconciliation'])->name('attendance-payroll-reconciliation');
            Route::get('/insurance-monthly', [Admin\ReportsController::class, 'insuranceMonthly'])->name('insurance-monthly');
            Route::get('/insurance-monthly/export/pdf', [Admin\ReportsController::class, 'insuranceMonthlyExportPdf'])->name('insurance-monthly.export.pdf');
            Route::get('/insurance-monthly/export/excel', [Admin\ReportsController::class, 'insuranceMonthlyExportExcel'])->name('insurance-monthly.export.excel');
            
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
            Route::get('/bank-transactions/export/pdf', [Admin\ReportsController::class, 'bankTransactionsExportPdf'])->name('bank-transactions.export.pdf');
            Route::get('/bank-transactions/export/excel', [Admin\ReportsController::class, 'bankTransactionsExportExcel'])->name('bank-transactions.export.excel');
            Route::get('/bank-transactions/document/{document}/lines', [Admin\ReportsController::class, 'bankStatementDocumentLines'])->name('bank-transactions.document.lines');
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
            Route::get('/vat-compliance', [Admin\VatComplianceController::class, 'index'])->name('vat-compliance');
            Route::post('/vat-remittances', [Admin\VatComplianceController::class, 'storeRemittance'])->name('vat-remittances.store');
            Route::post('/vat-declarations', [Admin\VatComplianceController::class, 'createDeclaration'])->name('vat-declarations.store');
            Route::post('/vat-declarations/{declaration}/submit', [Admin\VatComplianceController::class, 'submitDeclaration'])->name('vat-declarations.submit');
            Route::get('/vat-declarations/{declaration}/export-official', [Admin\VatComplianceController::class, 'exportDeclarationOfficial'])->name('vat-declarations.export-official');
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
            
            // گزارش‌های Party-Based
            Route::get('/party-balances', [Admin\ReportsController::class, 'partyBalances'])->name('party-balances');
            Route::get('/party/{partyId}/statement', [Admin\ReportsController::class, 'partyStatement'])->name('party-statement');
            Route::get('/party/{partyId}/profitability', [Admin\ReportsController::class, 'partyProfitability'])->name('party-profitability');
            Route::get('/all-parties-profitability', [Admin\ReportsController::class, 'allPartiesProfitability'])->name('all-parties-profitability');
            Route::get('/customer/{customerId}/supplier/{supplierId}/profitability', [Admin\ReportsController::class, 'customerSupplierProfitability'])->name('customer-supplier-profitability');
            Route::get('/parties-with-both-roles', [Admin\ReportsController::class, 'partiesWithBothRoles'])->name('parties-with-both-roles');
            Route::get('/party/{partyId}/aging-analysis', [Admin\ReportsController::class, 'partyAgingAnalysis'])->name('party-aging-analysis');
        });
    });
