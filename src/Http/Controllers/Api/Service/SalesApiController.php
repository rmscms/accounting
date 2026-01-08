<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\CustomerPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Service API Controller for Sales
 * برای ارتباط با پکیج shop
 * 
 * @group Service API - Sales
 */
class SalesApiController
{
    protected CustomerInvoiceService $invoiceService;
    protected CustomerPaymentService $paymentService;

    public function __construct(
        CustomerInvoiceService $invoiceService,
        CustomerPaymentService $paymentService
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    /**
     * Record sales invoice from shop package
     * 
     * @bodyParam order_id int required ID سفارش در shop
     * @bodyParam customer_id int required ID مشتری
     * @bodyParam store_id int required ID فروشگاه
     * @bodyParam invoice_date date required تاریخ فاکتور
     * @bodyParam total_amount numeric required مبلغ کل
     * @bodyParam tax_amount numeric مبلغ مالیات
     * @bodyParam items array required آیتم‌های فاکتور
     */
    public function recordInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'store_id' => 'required|integer',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency_code' => 'required|string|max:3',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice($validated);

            return response()->json([
                'success' => true,
                'message' => 'Invoice recorded successfully',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $invoice->total_amount,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record payment from shop package
     * 
     * @bodyParam order_id int required ID سفارش
     * @bodyParam customer_id int required ID مشتری
     * @bodyParam invoice_id int ID فاکتور (اختیاری)
     * @bodyParam amount numeric required مبلغ پرداخت
     * @bodyParam payment_method_id int required روش پرداخت
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'customer_invoice_id' => 'nullable|integer|exists:customer_invoices,id',
            'store_id' => 'required|integer',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'reference_number' => 'nullable|string',
            'transaction_id' => 'nullable|string',
        ]);

        try {
            $payment = $this->paymentService->recordPayment($validated);

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
