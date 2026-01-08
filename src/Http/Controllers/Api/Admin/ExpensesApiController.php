<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\Expense;
use RMS\Accounting\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Expenses
 * 
 * @group Admin API - Expenses
 */
class ExpensesApiController
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    /**
     * List all expenses
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('category')
            ->orderBy('expense_date', 'desc');

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('expense_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('expense_date', '<=', $request->end_date);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $expenses = $query->paginate($perPage);

        return response()->json($expenses);
    }

    /**
     * Get expense details
     */
    public function show(int $id): JsonResponse
    {
        $expense = Expense::with('category')->findOrFail($id);

        return response()->json([
            'data' => $expense,
        ]);
    }

    /**
     * Create new expense
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'expense_category_id' => 'required|integer|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'description' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $expense = $this->expenseService->createExpense($validated);

        return response()->json([
            'message' => trans('accounting::accounting.created_successfully'),
            'data' => $expense,
        ], 201);
    }

    /**
     * Update expense (only if pending)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update non-pending expense',
            ], 422);
        }

        $validated = $request->validate([
            'expense_category_id' => 'required|integer|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json([
            'message' => trans('accounting::accounting.updated_successfully'),
            'data' => $expense->fresh(),
        ]);
    }

    /**
     * Delete expense (only if pending)
     */
    public function destroy(int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete non-pending expense',
            ], 403);
        }

        $expense->delete();

        return response()->json([
            'message' => trans('accounting::accounting.deleted_successfully'),
        ]);
    }
}
