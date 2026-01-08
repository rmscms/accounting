<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\Supplier;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر مدیریت تامین‌کنندگان
 */
class SuppliersController extends AdminController
{
    protected string $model = Supplier::class;
    protected string $indexView = 'accounting::admin.suppliers.index';
    protected string $formView = 'accounting::admin.suppliers.form';

    public function index(Request $request)
    {
        $query = Supplier::orderBy('name');

        if ($request->filled('active')) {
            $query->where('active', $request->active);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $suppliers = $query->paginate(50);

        return view($this->indexView, compact('suppliers'));
    }

    public function form(?int $id = null)
    {
        $supplier = $id ? Supplier::findOrFail($id) : new Supplier();

        return view($this->formView, compact('supplier'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'active' => 'boolean',
        ]);

        Supplier::create($validated);

        return redirect()
            ->route('admin.accounting.suppliers.index')
            ->with('success', trans('accounting::accounting.supplier_created'));
    }

    public function update(Request $request, int $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'active' => 'boolean',
        ]);

        $supplier->update($validated);

        return redirect()
            ->route('admin.accounting.suppliers.index')
            ->with('success', trans('accounting::accounting.supplier_updated'));
    }

    public function show(int $id)
    {
        $supplier = Supplier::with(['purchaseOrders', 'supplierInvoices', 'supplierPayments'])
            ->findOrFail($id);

        // محاسبه آمار
        $stats = [
            'total_orders' => $supplier->purchaseOrders()->count(),
            'total_invoices' => $supplier->supplierInvoices()->count(),
            'total_paid' => $supplier->supplierPayments()->where('status', 'completed')->sum('amount'),
            'total_debt' => $supplier->supplierInvoices()->sum('balance_due'),
        ];

        return view('accounting::admin.suppliers.show', compact('supplier', 'stats'));
    }

    public function destroy(int $id)
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->supplierInvoices()->exists() || $supplier->purchaseOrders()->exists()) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.supplier_has_transactions')
            ], 403);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.supplier_deleted')
        ]);
    }
}
