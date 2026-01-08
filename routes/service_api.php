<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\ServiceApi;

/*
|--------------------------------------------------------------------------
| Service API Routes - Accounting Package
|--------------------------------------------------------------------------
| این API برای ارتباط با سایر پکیج‌ها (مثل shop و inventory) است
*/

Route::prefix('api/service/accounting')
    ->middleware(['api', 'service-api-auth'])
    ->name('api.service.accounting.')
    ->group(function () {
        
        // ثبت فاکتور فروش (از shop)
        Route::post('sales/record-invoice', [ServiceApi\SalesApiController::class, 'recordInvoice']);
        
        // ثبت دریافت (از shop)
        Route::post('sales/record-payment', [ServiceApi\SalesApiController::class, 'recordPayment']);
        
        // دریافت مانده مشتری
        Route::get('customers/{id}/balance', [ServiceApi\CustomersApiController::class, 'getBalance']);
        
        // ثبت هزینه خرید (از inventory)
        Route::post('purchases/record-invoice', [ServiceApi\PurchasesApiController::class, 'recordInvoice']);
        
        // ثبت پرداخت به تامین‌کننده (از inventory)
        Route::post('purchases/record-payment', [ServiceApi\PurchasesApiController::class, 'recordPayment']);
        
        // ثبت بهای تمام شده (COGS)
        Route::post('inventory/record-cogs', [ServiceApi\InventoryApiController::class, 'recordCOGS']);
        
        // دریافت نرخ ارز فعلی
        Route::get('currencies/{code}/rate', [ServiceApi\CurrenciesApiController::class, 'getCurrentRate']);
        
        // Health Check
        Route::get('health', fn() => response()->json(['status' => 'ok', 'service' => 'accounting']));
    });
