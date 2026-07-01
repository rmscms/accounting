<?php

namespace RMS\Accounting;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/accounting.php', 'accounting');
        $this->mergeConfigFrom(__DIR__.'/../config/admin_api.php', 'accounting.admin_api');
        $this->mergeConfigFrom(__DIR__.'/../config/service_api.php', 'accounting.service_api');
        
        // Register Core Services as singletons
        $this->app->singleton(\RMS\Accounting\Services\LedgerService::class);
        $this->app->singleton(\RMS\Accounting\Services\ChequeLedgerService::class);
        $this->app->singleton(\RMS\Accounting\Services\ChequeClearingAccountSetupService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingInstallService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingReadinessService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingSampleDataService::class);
        $this->app->singleton(\RMS\Accounting\Services\DocumentService::class);
        $this->app->singleton(\RMS\Accounting\Services\AuditActorResolver::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountService::class);
        $this->app->singleton(\RMS\Accounting\Services\CurrencyService::class);
        $this->app->singleton(\RMS\Accounting\Support\AuditColumnWriter::class);
        $this->app->singleton(\RMS\Accounting\Services\LedgerFxResolver::class);
        $this->app->singleton(\RMS\Accounting\Services\FiscalYearService::class);
        $this->app->singleton(\RMS\Accounting\Services\FiscalYearCloseOrchestrationService::class);
        
        // Register AR Services
        $this->app->singleton(\RMS\Accounting\Services\CustomerInvoiceService::class);
        $this->app->singleton(\RMS\Accounting\Services\CustomerPaymentService::class);
        $this->app->singleton(\RMS\Accounting\Services\CustomerBalanceService::class);
        
        // Register AP Services
        $this->app->singleton(\RMS\Accounting\Services\PurchaseOrderService::class);
        $this->app->singleton(\RMS\Accounting\Services\SupplierInvoiceService::class);
        $this->app->singleton(\RMS\Accounting\Services\SupplierPaymentService::class);
        
        // Register Other Services
        $this->app->singleton(\RMS\Accounting\Services\COGSService::class);
        $this->app->singleton(\RMS\Accounting\Services\TaxService::class);
        $this->app->singleton(\RMS\Accounting\Services\ExpenseService::class);
        $this->app->singleton(\RMS\Accounting\Services\ReconciliationService::class);
        $this->app->singleton(\RMS\Accounting\Services\SettlementService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingDateInputNormalizer::class);
        $this->app->singleton(\RMS\Accounting\Services\ReportService::class);
        $this->app->singleton(\RMS\Accounting\Services\VatRemittanceService::class);
        $this->app->singleton(\RMS\Accounting\Services\VatDeclarationService::class);
        $this->app->singleton(\RMS\Accounting\Services\PaymentDestinationCatalog::class);
        $this->app->singleton(\RMS\Accounting\Services\FiscalYearClosingService::class);
        
        // Register Party Services
        $this->app->singleton(\RMS\Accounting\Services\PartyService::class);
        $this->app->singleton(\RMS\Accounting\Services\PartyBalanceService::class);

        // Register Returns & Refunds Services (NEW)
        $this->app->singleton(\RMS\Accounting\Services\CreditNoteService::class);
        $this->app->singleton(\RMS\Accounting\Services\DebitNoteService::class);
        $this->app->singleton(\RMS\Accounting\Services\RefundService::class);
        $this->app->singleton(\RMS\Accounting\Services\AdvancePaymentService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccrualService::class);
        $this->app->singleton(\RMS\Accounting\Services\BadDebtService::class);

        // Register Advanced Accounting Services (Phase 2)
        $this->app->singleton(\RMS\Accounting\Services\FixedAssetService::class);
        $this->app->singleton(\RMS\Accounting\Services\BankTransferService::class);
        $this->app->singleton(\RMS\Accounting\Services\BankTransactionService::class);
        $this->app->singleton(\RMS\Accounting\Services\ManualJournalService::class);
        $this->app->singleton(\RMS\Accounting\Services\InventoryAdjustmentService::class);
        $this->app->singleton(\RMS\Accounting\Services\ExpenseCategoryCodeService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingAttachmentService::class);
        $this->app->singleton(\RMS\Accounting\Services\ExpenseStatusHistoryService::class);

        $this->app->singleton(\RMS\Accounting\Services\SystemAccountLocator::class);
        $this->app->singleton(\RMS\Accounting\Services\ShareholderAccountProvisioningService::class);
        $this->app->singleton(\RMS\Accounting\Services\EmployeeAccountProvisioningService::class);
        $this->app->singleton(\RMS\Accounting\Services\TreasurySubAccountProvisioningService::class);
        $this->app->singleton(\RMS\Accounting\Services\ShareholderWithdrawalService::class);
        $this->app->singleton(\RMS\Accounting\Services\ShareholderCapitalContributionService::class);
        $this->app->singleton(\RMS\Accounting\Services\PayrollInsuranceJournalService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountingDataWipeService::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \RMS\Accounting\Console\AccountingInstallCommand::class,
                \RMS\Accounting\Console\CloseFiscalYearCommand::class,
                \RMS\Accounting\Console\ChequeReminderCommand::class,
                \RMS\Accounting\Console\SetupChequeClearingAccountsCommand::class,
                \RMS\Accounting\Console\UpdateExchangeRatesCommand::class,
                \RMS\Accounting\Console\AutoReconcileCommand::class,
                \RMS\Accounting\Console\SimulateAccountingDataCommand::class,
                \RMS\Accounting\Console\GenerateAccountingDocumentsCommand::class,
                \RMS\Accounting\Console\AccountingWipeCommand::class,
                \RMS\Accounting\Console\BuildOldCrmCustomerSnapshotCommand::class,
                \RMS\Accounting\Console\CheckSampleDataConsistencyCommand::class,
                \RMS\Accounting\Console\AccountingHealthCommand::class,
            ]);
        }
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/accounting.php' => config_path('accounting.php'),
            __DIR__.'/../config/admin_api.php' => config_path('accounting/admin_api.php'),
            __DIR__.'/../config/service_api.php' => config_path('accounting/service_api.php'),
        ], 'accounting-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'accounting-migrations');

        // Publish views — منبع canonical همین پکیج است؛ پس از تغییر ویوها در میزبان: php artisan vendor:publish --tag=accounting-views --force
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/accounting'),
        ], 'accounting-views');

        // Publish translations
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/accounting'),
        ], 'accounting-lang');

        // Publish assets (CSS/JS)
        $this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/accounting'),
        ], 'accounting-assets');

        // Load routes
        if (config('accounting.routes.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }
        
        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Admin API routes are disabled until controllers are created
        // if (config('accounting.admin_api.enabled', true)) {
        //     $this->loadRoutesFrom(__DIR__.'/../routes/admin_api.php');
        // }

        if (config('accounting.service_api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/service_api.php');
        }

        // Load views (مسیر publish‌شدهٔ resources/views/vendor/accounting توسط خود Laravel قبل از پکیج ثبت می‌شود)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accounting');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'accounting');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'accounting');

        // Load migrations (در محیط development و testing)
        if ($this->app->environment(['local', 'development', 'testing'])) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
