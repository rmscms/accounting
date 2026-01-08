<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر فاکتورهای مشتریان
 */
class CustomerInvoicesController extends AdminController
{
    protected string $model = CustomerInvoice::class;
    protected string $indexView = 'accounting::admin.customer-invoices.index';
    protected string $formView = 'accounting::admin.customer-invoices.form';
    
    protected CustomerInvoiceService $invoiceService;

    public function __construct(CustomerInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * لیست فاکتورها
     */
    public function index(Request $request)
    {
        $query = CustomerInvoice::with(['currency', 'document'])
            ->orderByDesc('invoice_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        $invoices = $query->paginate(50);

        // فاکتورهای معوق
        $overdueCount = CustomerInvoice::overdue()->count();

        return view($this->indexView, compact('invoices', 'overdueCount'));
    }

    /**
     * فرم ایجاد/ویرایش فاکتور
     */
    public function form(?int $id = null)
    {
        $invoice = $id ? CustomerInvoice::with('items')->findOrFail($id) : new CustomerInvoice();

        return view($this->formView, compact('invoice'));
    }

    /**
     * ذخیره فاکتور
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'store_id' => 'required|integer',
            'order_id' => 'nullable|integer',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'fx_rate_at_invoice' => 'required|numeric|min:0',
            'status' => 'required|in:draft,issued,cancelled',
            'notes' => 'nullable|string',
        ]);

        $invoice = $this->invoiceService->createInvoice($validated);

        return redirect()
            ->route('admin.accounting.customer-invoices.index')
            ->with('success', trans('accounting::accounting.invoice_created'));
    }

    /**
     * نمایش جزئیات فاکتور
     */
    public function show(int $id)
    {
        $invoice = CustomerInvoice::with(['currency', 'document', 'payments'])
            ->findOrFail($id);

        return view('accounting::admin.customer-invoices.show', compact('invoice'));
    }
}
