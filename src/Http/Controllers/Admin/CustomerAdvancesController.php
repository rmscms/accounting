<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\CustomerAdvance;
use RMS\Accounting\Services\AdvancePaymentService;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerAdvancesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected AdvancePaymentService $advanceService;

    public function __construct(Filesystem $filesystem, AdvancePaymentService $service)
    {
        parent::__construct($filesystem);
        $this->advanceService = $service;
    }

    public function table(): string
    {
        return 'customer_advances';
    }

    public function modelName(): string
    {
        return CustomerAdvance::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.customer-advances';
    }

    public function routeParameter(): string
    {
        return 'customer_advance';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('customer_id', 'مشتری')->setOptions($this->getCustomerOptions())->required(),
            Field::number('amount', 'مبلغ')->required(),
            Field::select('payment_method', 'روش پرداخت')->setOptions([
                'cash' => 'نقدی',
                'bank_transfer' => 'انتقال بانکی',
                'card_transfer' => 'کارت به کارت',
                'cheque' => 'چک',
                'online' => 'آنلاین',
                'pos' => 'POS',
            ])->required(),
            Field::date('advance_date', 'تاریخ')->withDefaultValue(now()),
            Field::textarea('notes', 'یادداشت')->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('advance_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('customer.name')->withTitle('مشتری')->searchable(),
            Field::make('amount')->withTitle('مبلغ کل')->customMethod('renderAmount'),
            Field::make('remaining_amount')->withTitle('مانده')->customMethod('renderRemaining')->sortable(),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus'),
            Field::date('advance_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required'],
            'advance_date' => ['required', 'date'],
        ];
    }

    public function apply(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:customer_invoices,id',
            'amount' => 'required|numeric|min:0'
        ]);
        
        $this->advanceService->applyCustomerAdvanceToInvoice($id, $validated['invoice_id'], $validated['amount']);
        
        return redirect()->back()->with('success', 'پیش دریافت به فاکتور اعمال شد');
    }

    protected function getCustomerOptions(): array
    {
        return \RMS\Accounting\Models\Customer::where('active', true)->pluck('name', 'id')->toArray();
    }

    public function renderAmount($row): string
    {
        return number_format($row->amount) . ' تومان';
    }

    public function renderRemaining($row): string
    {
        return '<span class="text-primary font-weight-bold">' . number_format($row->remaining_amount) . ' تومان</span>';
    }

    public function renderStatus($row): string
    {
        $badges = [
            'active' => '<span class="badge badge-success">فعال</span>',
            'fully_applied' => '<span class="badge badge-secondary">اعمال شده</span>',
            'refunded' => '<span class="badge badge-warning">برگشت داده شده</span>',
            'cancelled' => '<span class="badge badge-danger">لغو شده</span>',
        ];
        return $badges[$row->status] ?? $row->status;
    }
}
