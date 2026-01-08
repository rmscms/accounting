<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Services\SupplierInvoiceService;
use RMS\Accounting\Services\SupplierPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Service API Controller for Purchases
 * برای ارتباط با پکیج inventory
 * 
 * @group Service API - Purchases
 */
class PurchasesApiController
{
    protected SupplierInvoiceService $invoiceService;
    protected SupplierPaymentService $paymentService;

    public function __construct(
        SupplierInvoiceService $invoiceService,
        SupplierPaymentService $paymentService
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    /**
     * Record purchase invoice from inventory package
     * 
     * @bodyParam purchase_id int required ID خرید در inventory
     * @bodyParam supplier_id int required ID تامین‌کننده
     * @bodyParam store_id int required ID فروشگاه
     * @bodyParam invoice_date date required تاریخ فاکتور
     * @bodyParam total_amount numeric required مبلغ کل
     */
    public function recordInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'store_id' => 'required|integer',
            'purchase_order_id' => 'nullable|integer',
            'invoice_number' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency_code' => 'required|string|max:3',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice($validated);

            return response()->json([
                'success' => true,
                'message' => 'Purchase invoice recorded successfully',
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
     * Record payment to supplier from inventory package
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer',
            'supplier_invoice_id' => 'nullable|integer|exists:supplier_invoices,id',
            'store_id' => 'required|integer',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'reference_number' => 'nullable|string',
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
