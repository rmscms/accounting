<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Services\CustomerPaymentService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر دریافت‌های مشتریان
 */
class CustomerPaymentsController extends AdminController
{
    protected string $model = CustomerPayment::class;
    protected string $indexView = 'accounting::admin.customer-payments.index';
    protected string $formView = 'accounting::admin.customer-payments.form';
    
    protected CustomerPaymentService $paymentService;

    public function __construct(CustomerPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $query = CustomerPayment::with(['paymentMethod', 'bank', 'document'])
            ->orderByDesc('payment_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        $payments = $query->paginate(50);

        // آمار
        $totalCompleted = CustomerPayment::where('status', 'completed')
            ->sum('amount');

        $totalPending = CustomerPayment::where('status', 'pending')
            ->sum('amount');

        return view($this->indexView, compact('payments', 'totalCompleted', 'totalPending'));
    }

    public function form(?int $id = null)
    {
        $payment = $id ? CustomerPayment::with('customerInvoice')->findOrFail($id) : new CustomerPayment();

        $paymentMethods = \RMS\Accounting\Models\PaymentMethod::where('active', true)
            ->pluck('name', 'id');

        $banks = \RMS\Accounting\Models\Bank::where('active', true)
            ->pluck('name', 'id');

        return view($this->formView, compact('payment', 'paymentMethods', 'banks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'customer_invoice_id' => 'nullable|exists:customer_invoices,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'fx_rate_at_payment' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'bank_id' => 'nullable|exists:banks,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'required|in:pending,completed,failed,reversed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $this->paymentService->createPayment($validated);

        return redirect()
            ->route('admin.accounting.customer-payments.index')
            ->with('success', trans('accounting::accounting.payment_created'));
    }

    public function show(int $id)
    {
        $payment = CustomerPayment::with([
            'customerInvoice',
            'paymentMethod',
            'bank',
            'cashBox',
            'document.ledgers'
        ])->findOrFail($id);

        return view('accounting::admin.customer-payments.show', compact('payment'));
    }
}
