<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Services\CustomerPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Customer Payments
 * 
 * @group Admin API - Customer Payments
 */
class CustomerPaymentsApiController
{
    protected CustomerPaymentService $paymentService;

    public function __construct(CustomerPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * List all customer payments
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerPayment::with('invoice')
            ->orderBy('payment_date', 'desc');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('payment_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('payment_date', '<=', $request->end_date);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $payments = $query->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Get payment details
     */
    public function show(int $id): JsonResponse
    {
        $payment = CustomerPayment::with('invoice')->findOrFail($id);

        return response()->json([
            'data' => $payment,
        ]);
    }

    /**
     * Record new payment
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'customer_invoice_id' => 'nullable|integer|exists:customer_invoices,id',
            'store_id' => 'required|integer',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->recordPayment($validated);

        return response()->json([
            'message' => trans('accounting::accounting.created_successfully'),
            'data' => $payment,
        ], 201);
    }

    /**
     * Confirm payment
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payment = CustomerPayment::findOrFail($id);

        if ($payment->status === 'confirmed') {
            return response()->json([
                'message' => 'Payment already confirmed',
            ], 422);
        }

        $payment->status = 'confirmed';
        $payment->confirmed_at = now();
        $payment->save();

        return response()->json([
            'message' => 'Payment confirmed successfully',
            'data' => $payment,
        ]);
    }

    /**
     * Delete payment
     */
    public function destroy(int $id): JsonResponse
    {
        $payment = CustomerPayment::findOrFail($id);

        if ($payment->status === 'confirmed') {
            return response()->json([
                'message' => 'Cannot delete confirmed payment',
            ], 403);
        }

        $payment->delete();

        return response()->json([
            'message' => trans('accounting::accounting.deleted_successfully'),
        ]);
    }
}
