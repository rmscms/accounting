<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\Api\TaxApiController;

/*
|--------------------------------------------------------------------------
| API Routes - Accounting Package
|--------------------------------------------------------------------------
*/

Route::prefix('api/accounting')
    ->middleware(['api'])
    ->name('api.accounting.')
    ->group(function () {
        $taxApiMiddleware = ['throttle:60,1', 'api.idempotency'];
        if ((bool) config('accounting.security.tax_api_require_api_key', false)) {
            $taxApiMiddleware[] = 'auth.api';
        }
        $taxApiScope = trim((string) config('accounting.security.tax_api_scope', ''));
        if ($taxApiScope !== '') {
            $taxApiMiddleware[] = 'api.scope:'.$taxApiScope;
        }

        // Tax API Endpoints
        Route::prefix('tax')->middleware($taxApiMiddleware)->name('tax.')->group(function () {
            Route::get('/settings', [TaxApiController::class, 'getSettings'])->name('settings');
            Route::post('/calculate-vat', [TaxApiController::class, 'calculateVAT'])->name('calculate-vat');
            Route::post('/calculate-income-tax', [TaxApiController::class, 'calculateIncomeTax'])->name('calculate-income-tax');
            Route::get('/vat-rates', [TaxApiController::class, 'getVATRates'])->name('vat-rates');
            Route::get('/vat-payable', [TaxApiController::class, 'getVATPayable'])->name('vat-payable');
            Route::post('/calculate-multiple', [TaxApiController::class, 'calculateMultiple'])->name('calculate-multiple');
        });
    });
