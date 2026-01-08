<?php

namespace RMS\Accounting;

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
        $this->app->singleton(\RMS\Accounting\Services\DocumentService::class);
        $this->app->singleton(\RMS\Accounting\Services\AccountService::class);
        $this->app->singleton(\RMS\Accounting\Services\CurrencyService::class);
        $this->app->singleton(\RMS\Accounting\Services\FiscalYearService::class);
        
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
        $this->app->singleton(\RMS\Accounting\Services\ReportService::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \RMS\Accounting\Console\Commands\AccountingInstallCommand::class,
                \RMS\Accounting\Console\Commands\CloseFiscalYearCommand::class,
                \RMS\Accounting\Console\Commands\ChequeReminderCommand::class,
                \RMS\Accounting\Console\Commands\UpdateExchangeRatesCommand::class,
                \RMS\Accounting\Console\Commands\RecalculateBalancesCommand::class,
                \RMS\Accounting\Console\Commands\AutoReconcileCommand::class,
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

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/accounting'),
        ], 'accounting-views');

        // Publish translations
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/accounting'),
        ], 'accounting-lang');

        // Load routes
        if (config('accounting.routes.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        if (config('accounting.admin_api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin_api.php');
        }

        if (config('accounting.service_api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/service_api.php');
        }

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accounting');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'accounting');

        // Load migrations (در محیط development و testing)
        if ($this->app->environment(['local', 'development', 'testing'])) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
