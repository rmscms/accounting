<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Services\CustomerInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Customer Invoices
 * 
 * @group Admin API - Customer Invoices
 */
class CustomerInvoicesApiController
{
    protected CustomerInvoiceService $invoiceService;

    public function __construct(CustomerInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * List all customer invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerInvoice::with('items')
            ->orderBy('invoice_date', 'desc');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('invoice_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('invoice_date', '<=', $request->end_date);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $invoices = $query->paginate($perPage);

        return response()->json($invoices);
    }

    /**
     * Get invoice details
     */
    public function show(int $id): JsonResponse
    {
        $invoice = CustomerInvoice::with('items')->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * Create new invoice
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'store_id' => 'required|integer',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'currency_code' => 'required|string|max:3',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        $invoice = $this->invoiceService->createInvoice($validated);

        return response()->json([
            'message' => trans('accounting::accounting.created_successfully'),
            'data' => $invoice,
        ], 201);
    }

    /**
     * Update invoice (only if not paid)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = CustomerInvoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Cannot update paid invoice',
            ], 422);
        }

        $validated = $request->validate([
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return response()->json([
            'message' => trans('accounting::accounting.updated_successfully'),
            'data' => $invoice->fresh(),
        ]);
    }

    /**
     * Delete invoice (only if not paid)
     */
    public function destroy(int $id): JsonResponse
    {
        $invoice = CustomerInvoice::findOrFail($id);

        if ($invoice->status === 'paid' || $invoice->paid_amount > 0) {
            return response()->json([
                'message' => 'Cannot delete invoice with payments',
            ], 403);
        }

        $invoice->delete();

        return response()->json([
            'message' => trans('accounting::accounting.deleted_successfully'),
        ]);
    }
}
