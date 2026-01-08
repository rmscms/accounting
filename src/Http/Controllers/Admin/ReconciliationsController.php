<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\PaymentReconciliation;
use RMS\Accounting\Services\ReconciliationService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر تطبیق پرداخت‌ها
 */
class ReconciliationsController extends AdminController
{
    protected string $model = PaymentReconciliation::class;
    protected string $indexView = 'accounting::admin.reconciliations.index';
    protected string $formView = 'accounting::admin.reconciliations.form';
    
    protected ReconciliationService $reconciliationService;

    public function __construct(ReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * لیست تطبیق‌ها
     */
    public function index(Request $request)
    {
        $query = PaymentReconciliation::with(['bank', 'cashBox', 'posTerminal'])
            ->orderByDesc('reconciliation_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reconciliation_type')) {
            $query->where('reconciliation_type', $request->reconciliation_type);
        }

        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }

        if ($request->filled('is_reconciled')) {
            $query->where('is_reconciled', $request->is_reconciled);
        }

        $reconciliations = $query->paginate(50);

        // آمار
        $pendingCount = PaymentReconciliation::pending()->count();
        $discrepancyCount = PaymentReconciliation::withDiscrepancy()->count();

        return view($this->indexView, compact('reconciliations', 'pendingCount', 'discrepancyCount'));
    }

    /**
     * فرم ایجاد تطبیق
     */
    public function form(?int $id = null)
    {
        $reconciliation = $id ? PaymentReconciliation::findOrFail($id) : new PaymentReconciliation();

        $banks = \RMS\Accounting\Models\Bank::where('active', true)->get();
        $cashBoxes = \RMS\Accounting\Models\CashBox::where('active', true)->get();
        $posTerminals = \RMS\Accounting\Models\POSTerminal::where('active', true)->get();

        return view($this->formView, compact('reconciliation', 'banks', 'cashBoxes', 'posTerminals'));
    }

    /**
     * ذخیره تطبیق
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'nullable|integer',
            'reconciliation_type' => 'required|in:bank,cash_box,pos,general',
            'bank_id' => 'nullable|exists:banks,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'pos_terminal_id' => 'nullable|exists:pos_terminals,id',
            'expected_amount' => 'required|numeric|min:0',
            'actual_amount' => 'required|numeric|min:0',
            'reconciliation_date' => 'required|date',
            'bank_statement_reference' => 'nullable|string|max:255',
            'receipt_image' => 'nullable|string|max:500',
            'discrepancy_notes' => 'nullable|string',
        ]);

        $reconciliation = $this->reconciliationService->createReconciliation($validated);

        return redirect()
            ->route('admin.accounting.reconciliations.index')
            ->with('success', trans('accounting::accounting.reconciliation_created'));
    }

    /**
     * تایید تطبیق (Checkbox Confirmation)
     */
    public function confirm(Request $request, int $id)
    {
        $validated = $request->validate([
            'discrepancy_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->reconciliationService->confirmReconciliation(
                $id,
                $validated['discrepancy_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.reconciliation_confirmed')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * تطبیق خودکار
     */
    public function autoReconcile(Request $request)
    {
        $validated = $request->validate([
            'bank_id' => 'nullable|exists:banks,id',
            'date' => 'nullable|date',
        ]);

        $results = $this->reconciliationService->autoReconcilePayments(
            $validated['bank_id'] ?? null,
            $validated['date'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.auto_reconcile_completed'),
            'data' => $results
        ]);
    }

    /**
     * نمایش جزئیات تطبیق
     */
    public function show(int $id)
    {
        $reconciliation = PaymentReconciliation::with(['bank', 'cashBox', 'posTerminal'])
            ->findOrFail($id);

        return view('accounting::admin.reconciliations.show', compact('reconciliation'));
    }
}
