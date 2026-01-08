<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Models\CustomerBalance;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Service API Controller for Customers
 * برای دریافت اطلاعات مشتریان توسط پکیج‌های دیگر
 * 
 * @group Service API - Customers
 */
class CustomersApiController
{
    /**
     * Get customer balance
     * 
     * @urlParam id int required ID مشتری
     * @queryParam store_id int ID فروشگاه (اختیاری)
     */
    public function getBalance(int $id, Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');

        // Calculate total invoiced
        $invoicesQuery = CustomerInvoice::where('customer_id', $id);
        if ($storeId) {
            $invoicesQuery->where('store_id', $storeId);
        }
        $totalInvoiced = $invoicesQuery->sum('total_amount');
        $totalPaid = $invoicesQuery->sum('paid_amount');

        // Calculate total payments
        $paymentsQuery = CustomerPayment::where('customer_id', $id)
            ->where('status', 'confirmed');
        if ($storeId) {
            $paymentsQuery->where('store_id', $storeId);
        }
        $totalPayments = $paymentsQuery->sum('amount');

        // Get balance from view (if available)
        $balanceRecord = CustomerBalance::where('customer_id', $id)->first();

        $balance = $totalInvoiced - $totalPaid;

        return response()->json([
            'success' => true,
            'data' => [
                'customer_id' => $id,
                'store_id' => $storeId,
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'total_payments' => $totalPayments,
                'balance' => $balance,
                'currency_code' => 'IRR',
                'last_invoice_date' => $invoicesQuery->max('invoice_date'),
                'last_payment_date' => $paymentsQuery->max('payment_date'),
            ],
        ]);
    }
}
