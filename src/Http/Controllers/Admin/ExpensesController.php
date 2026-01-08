<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Services\ExpenseService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر مدیریت هزینه‌ها
 */
class ExpensesController extends AdminController
{
    protected string $model = Expense::class;
    protected string $indexView = 'accounting::admin.expenses.index';
    protected string $formView = 'accounting::admin.expenses.form';
    
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    /**
     * لیست هزینه‌ها
     */
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'currency', 'bank', 'cashBox'])
            ->orderByDesc('expense_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        $expenses = $query->paginate(50);

        // آمار
        $pendingCount = Expense::where('status', Expense::STATUS_PENDING)->count();
        $totalThisMonth = Expense::where('status', Expense::STATUS_APPROVED)
            ->whereMonth('expense_date', now()->month)
            ->sum('total_amount');

        return view($this->indexView, compact('expenses', 'pendingCount', 'totalThisMonth'));
    }

    /**
     * فرم ایجاد/ویرایش هزینه
     */
    public function form(?int $id = null)
    {
        $expense = $id ? Expense::with('items')->findOrFail($id) : new Expense();
        
        $categories = \RMS\Accounting\Models\ExpenseCategory::where('active', true)
            ->orderBy('name')
            ->get();

        $banks = \RMS\Accounting\Models\Bank::where('active', true)->get();
        $cashBoxes = \RMS\Accounting\Models\CashBox::where('active', true)->get();

        return view($this->formView, compact('expense', 'categories', 'banks', 'cashBoxes'));
    }

    /**
     * ذخیره هزینه
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'store_id' => 'required|integer',
            'expense_date' => 'required|date',
            'description' => 'required|string|max:500',
            'bank_id' => 'nullable|exists:banks,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'currency_code' => 'required|string|max:3',
            'fx_rate_at_expense' => 'required|numeric|min:0',
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string',
            'receipt_image' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_amount' => 'required|numeric|min:0',
        ]);

        $items = $validated['items'];
        unset($validated['items']);

        $expense = $this->expenseService->createExpense($validated, $items);

        return redirect()
            ->route('admin.accounting.expenses.index')
            ->with('success', trans('accounting::accounting.expense_created'));
    }

    /**
     * تایید هزینه
     */
    public function approve(int $id)
    {
        try {
            $this->expenseService->approveExpense($id);

            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.expense_approved')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * نمایش جزئیات هزینه
     */
    public function show(int $id)
    {
        $expense = Expense::with(['category', 'items', 'currency', 'bank', 'cashBox', 'document'])
            ->findOrFail($id);

        return view('accounting::admin.expenses.show', compact('expense'));
    }
}
