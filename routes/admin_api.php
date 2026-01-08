<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\AdminApi;

/*
|--------------------------------------------------------------------------
| Admin API Routes - Accounting Package
|--------------------------------------------------------------------------
*/

Route::prefix('api/admin/accounting')
    ->middleware(['api', 'auth:sanctum'])
    ->name('api.admin.accounting.')
    ->group(function () {
        
        // حساب‌ها
        Route::apiResource('accounts', AdminApi\AccountsApiController::class);
        Route::get('accounts/{id}/balance', [AdminApi\AccountsApiController::class, 'balance']);
        
        // دفتر کل
        Route::get('ledger', [AdminApi\LedgerApiController::class, 'index']);
        
        // اسناد
        Route::apiResource('documents', AdminApi\DocumentsApiController::class);
        Route::post('documents/{id}/post', [AdminApi\DocumentsApiController::class, 'post']);
        
        // فاکتورها
        Route::apiResource('customer-invoices', AdminApi\CustomerInvoicesApiController::class);
        
        // دریافت‌ها
        Route::apiResource('customer-payments', AdminApi\CustomerPaymentsApiController::class);
        
        // هزینه‌ها
        Route::apiResource('expenses', AdminApi\ExpensesApiController::class);
        
        // گزارش‌ها
        Route::get('reports/dashboard', [AdminApi\ReportsApiController::class, 'dashboard']);
        Route::get('reports/trial-balance', [AdminApi\ReportsApiController::class, 'trialBalance']);
    });
