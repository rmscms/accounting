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
    ->middleware(['api', 'service-api-auth', 'throttle:180,1', 'api.idempotency'])
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

        // ========================================
        // Credit Notes & Returns (NEW)
        // ========================================
        
        // ثبت اعتبار برگشتی (از shop)
        Route::post('sales/credit-note', [ServiceApi\SalesApiController::class, 'createCreditNote']);
        Route::post('sales/credit-note/{id}/issue', [ServiceApi\SalesApiController::class, 'issueCreditNote']);
        Route::post('sales/credit-note/{id}/apply', [ServiceApi\SalesApiController::class, 'applyCreditNote']);
        
        // ثبت یادداشت بدهکار (از inventory)
        Route::post('purchases/debit-note', [ServiceApi\PurchasesApiController::class, 'createDebitNote']);
        Route::post('purchases/debit-note/{id}/issue', [ServiceApi\PurchasesApiController::class, 'issueDebitNote']);
        Route::post('purchases/debit-note/{id}/apply', [ServiceApi\PurchasesApiController::class, 'applyDebitNote']);
        
        // ========================================
        // Refunds (NEW)
        // ========================================
        
        // بازگشت وجه به مشتری (از shop)
        Route::post('sales/refund', [ServiceApi\SalesApiController::class, 'processRefund']);
        
        // دریافت بازگشت از تامین‌کننده (از inventory)
        Route::post('purchases/refund', [ServiceApi\PurchasesApiController::class, 'receiveRefund']);
        
        // ========================================
        // Advance Payments (NEW)
        // ========================================
        
        // پیش دریافت از مشتری (از shop)
        Route::post('sales/advance', [ServiceApi\SalesApiController::class, 'receiveAdvance']);
        Route::post('sales/advance/{id}/apply', [ServiceApi\SalesApiController::class, 'applyAdvance']);
        
        // پیش پرداخت به تامین‌کننده (از inventory)
        Route::post('purchases/advance', [ServiceApi\PurchasesApiController::class, 'payAdvance']);
        Route::post('purchases/advance/{id}/apply', [ServiceApi\PurchasesApiController::class, 'applyAdvance']);
    });
