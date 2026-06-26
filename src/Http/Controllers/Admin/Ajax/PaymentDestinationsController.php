<?php

namespace RMS\Accounting\Http\Controllers\Admin\Ajax;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RMS\Accounting\Services\PaymentDestinationCatalog;

/**
 * JSON کاتالوگ کانال‌های تسویه — نام متد نمی‌تواند index باشد (تداخل با AdminController).
 */
class PaymentDestinationsController extends Controller
{
    public function catalog(Request $request, PaymentDestinationCatalog $catalog): JsonResponse
    {
        $context = (string) $request->query('context', PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT);

        return response()->json($catalog->build($context));
    }
}
