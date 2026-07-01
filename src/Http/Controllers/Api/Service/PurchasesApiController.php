<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Services\SupplierInvoiceService;
use RMS\Accounting\Services\SupplierPaymentService;
use RMS\Accounting\Services\DebitNoteService;
use RMS\Accounting\Services\RefundService;
use RMS\Accounting\Services\AdvancePaymentService;
use RMS\Accounting\Models\DebitNote;
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
    protected DebitNoteService $debitNoteService;
    protected RefundService $refundService;
    protected AdvancePaymentService $advancePaymentService;

    public function __construct(
        SupplierInvoiceService $invoiceService,
        SupplierPaymentService $paymentService,
        DebitNoteService $debitNoteService,
        RefundService $refundService,
        AdvancePaymentService $advancePaymentService
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->debitNoteService = $debitNoteService;
        $this->refundService = $refundService;
        $this->advancePaymentService = $advancePaymentService;
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

    public function createDebitNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'supplier_invoice_id' => 'nullable|integer|exists:supplier_invoices,id',
            'store_id' => 'required|integer',
            'debit_date' => 'nullable|date',
            'currency_code' => 'required|string|max:3',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'reason' => 'nullable|string|max:1000',
            'debit_type' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        $debitNote = $this->debitNoteService->createDebitNote($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $debitNote->id,
                'debit_note_number' => (string) $debitNote->debit_note_number,
                'status' => (string) $debitNote->status,
            ],
        ], 201);
    }

    public function issueDebitNote(int $id): JsonResponse
    {
        $debitNote = DebitNote::findOrFail($id);
        $issued = $this->debitNoteService->issueDebitNote($debitNote);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $issued->id,
                'status' => (string) $issued->status,
            ],
        ]);
    }

    public function applyDebitNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:supplier_invoices,id',
        ]);

        $debitNote = DebitNote::findOrFail($id);
        $applied = $this->debitNoteService->applyToInvoice($debitNote, (int) $validated['invoice_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $applied->id,
                'status' => (string) $applied->status,
                'invoice_id' => (int) $validated['invoice_id'],
            ],
        ]);
    }

    public function receiveRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'store_id' => 'required|integer',
            'debit_note_id' => 'nullable|integer|exists:debit_notes,id',
            'supplier_payment_id' => 'nullable|integer|exists:supplier_payments,id',
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

        $refund = $this->refundService->receiveSupplierRefund($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $refund->id,
                'refund_number' => (string) $refund->refund_number,
                'status' => (string) $refund->status,
            ],
        ], 201);
    }

    public function payAdvance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'store_id' => 'required|integer',
            'advance_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.000001',
            'currency_code' => 'required|string|max:3',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'payment_method' => 'nullable|string',
            'payment_method_id' => 'nullable|integer',
            'bank_id' => 'nullable|integer',
            'cash_box_id' => 'nullable|integer',
            'cheque_id' => 'nullable|integer',
            'pos_terminal_id' => 'nullable|integer',
            'wallet_id' => 'nullable|integer',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $advance = $this->advancePaymentService->paySupplierAdvance($validated);

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
            'invoice_id' => 'required|integer|exists:supplier_invoices,id',
            'amount' => 'required|numeric|min:0.000001',
        ]);

        $this->advancePaymentService->applySupplierAdvanceToInvoice(
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
