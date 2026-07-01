<?php

use Illuminate\Support\Facades\Route;
use RMS\Accounting\Http\Controllers\Api\Service\SalesApiController;
use RMS\Accounting\Http\Controllers\Api\Service\CustomersApiController;
use RMS\Accounting\Http\Controllers\Api\Service\PurchasesApiController;
use RMS\Accounting\Http\Controllers\Api\Service\InventoryApiController;
use RMS\Accounting\Http\Controllers\Api\Service\CurrenciesApiController;

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
        Route::post('sales/record-invoice', [SalesApiController::class, 'recordInvoice']);
        
        // ثبت دریافت (از shop)
        Route::post('sales/record-payment', [SalesApiController::class, 'recordPayment']);
        
        // دریافت مانده مشتری
        Route::get('customers/{id}/balance', [CustomersApiController::class, 'getBalance']);
        
        // ثبت هزینه خرید (از inventory)
        Route::post('purchases/record-invoice', [PurchasesApiController::class, 'recordInvoice']);
        
        // ثبت پرداخت به تامین‌کننده (از inventory)
        Route::post('purchases/record-payment', [PurchasesApiController::class, 'recordPayment']);
        
        // ثبت بهای تمام شده (COGS)
        Route::post('inventory/record-cogs', [InventoryApiController::class, 'recordCOGS']);
        Route::post('inventory/record-adjustment', [InventoryApiController::class, 'recordAdjustment']);
        Route::post('inventory/reverse-adjustment', [InventoryApiController::class, 'reverseAdjustment']);
        
        // دریافت نرخ ارز فعلی
        Route::get('currencies/{code}/rate', [CurrenciesApiController::class, 'getCurrentRate']);
        
        // Health Check
        Route::get('health', fn() => response()->json(['status' => 'ok', 'service' => 'accounting']));

        // ========================================
        // Credit Notes & Returns (NEW)
        // ========================================
        
        // ثبت اعتبار برگشتی (از shop)
        Route::post('sales/credit-note', [SalesApiController::class, 'createCreditNote']);
        Route::post('sales/credit-note/{id}/issue', [SalesApiController::class, 'issueCreditNote']);
        Route::post('sales/credit-note/{id}/apply', [SalesApiController::class, 'applyCreditNote']);
        
        // ثبت یادداشت بدهکار (از inventory)
        Route::post('purchases/debit-note', [PurchasesApiController::class, 'createDebitNote']);
        Route::post('purchases/debit-note/{id}/issue', [PurchasesApiController::class, 'issueDebitNote']);
        Route::post('purchases/debit-note/{id}/apply', [PurchasesApiController::class, 'applyDebitNote']);
        
        // ========================================
        // Refunds (NEW)
        // ========================================
        
        // بازگشت وجه به مشتری (از shop)
        Route::post('sales/refund', [SalesApiController::class, 'processRefund']);
        
        // دریافت بازگشت از تامین‌کننده (از inventory)
        Route::post('purchases/refund', [PurchasesApiController::class, 'receiveRefund']);
        
        // ========================================
        // Advance Payments (NEW)
        // ========================================
        
        // پیش دریافت از مشتری (از shop)
        Route::post('sales/advance', [SalesApiController::class, 'receiveAdvance']);
        Route::post('sales/advance/{id}/apply', [SalesApiController::class, 'applyAdvance']);
        
        // پیش پرداخت به تامین‌کننده (از inventory)
        Route::post('purchases/advance', [PurchasesApiController::class, 'payAdvance']);
        Route::post('purchases/advance/{id}/apply', [PurchasesApiController::class, 'applyAdvance']);
    });
