<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\CustomerPaymentService;
use RMS\Accounting\Services\CreditNoteService;
use RMS\Accounting\Services\RefundService;
use RMS\Accounting\Services\AdvancePaymentService;
use RMS\Accounting\Models\CreditNote;
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
    protected CreditNoteService $creditNoteService;
    protected RefundService $refundService;
    protected AdvancePaymentService $advancePaymentService;

    public function __construct(
        CustomerInvoiceService $invoiceService,
        CustomerPaymentService $paymentService,
        CreditNoteService $creditNoteService,
        RefundService $refundService,
        AdvancePaymentService $advancePaymentService
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->creditNoteService = $creditNoteService;
        $this->refundService = $refundService;
        $this->advancePaymentService = $advancePaymentService;
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

    public function createCreditNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'customer_invoice_id' => 'nullable|integer|exists:customer_invoices,id',
            'store_id' => 'required|integer',
            'credit_date' => 'nullable|date',
            'currency_code' => 'required|string|max:3',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'reason' => 'nullable|string|max:1000',
            'credit_type' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        $creditNote = $this->creditNoteService->createCreditNote($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $creditNote->id,
                'credit_note_number' => (string) $creditNote->credit_note_number,
                'status' => (string) $creditNote->status,
            ],
        ], 201);
    }

    public function issueCreditNote(int $id): JsonResponse
    {
        $creditNote = CreditNote::findOrFail($id);
        $issued = $this->creditNoteService->issueCreditNote($creditNote);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $issued->id,
                'status' => (string) $issued->status,
            ],
        ]);
    }

    public function applyCreditNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:customer_invoices,id',
        ]);

        $creditNote = CreditNote::findOrFail($id);
        $applied = $this->creditNoteService->applyToInvoice($creditNote, (int) $validated['invoice_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $applied->id,
                'status' => (string) $applied->status,
                'invoice_id' => (int) $validated['invoice_id'],
            ],
        ]);
    }

    public function processRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'store_id' => 'required|integer',
            'credit_note_id' => 'nullable|integer|exists:credit_notes,id',
            'customer_payment_id' => 'nullable|integer|exists:customer_payments,id',
            'refund_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.000001',
            'currency_code' => 'required|string|max:3',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'refund_method' => 'nullable|string',
            'bank_id' => 'nullable|integer',
            'cash_box_id' => 'nullable|integer',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $refund = $this->refundService->processCustomerRefund($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $refund->id,
                'refund_number' => (string) $refund->refund_number,
                'status' => (string) $refund->status,
            ],
        ], 201);
    }

    public function receiveAdvance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'store_id' => 'required|integer',
            'advance_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.000001',
            'currency_code' => 'required|string|max:3',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'payment_method' => 'nullable|string',
            'bank_id' => 'nullable|integer',
            'cash_box_id' => 'nullable|integer',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $advance = $this->advancePaymentService->receiveCustomerAdvance($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $advance->id,
                'advance_number' => (string) $advance->advance_number,
                'remaining_amount' => (float) $advance->remaining_amount,
            ],
        ], 201);
    }

    public function applyAdvance(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:customer_invoices,id',
            'amount' => 'required|numeric|min:0.000001',
        ]);

        $this->advancePaymentService->applyCustomerAdvanceToInvoice(
            $id,
            (int) $validated['invoice_id'],
            (float) $validated['amount']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'advance_id' => $id,
                'invoice_id' => (int) $validated['invoice_id'],
                'amount' => (float) $validated['amount'],
            ],
        ]);
    }
}
