<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Services\SupplierInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Data\StatCard;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Stats\HasStats;

class SupplierInvoicesController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    HasStats
{
    protected SupplierInvoiceService $invoiceService;

    public function __construct(SupplierInvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    public function table(): string
    {
        return 'supplier_invoices';
    }

    public function modelName(): string
    {
        return SupplierInvoice::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.supplier-invoices';
    }

    public function routeParameter(): string
    {
        return 'supplier_invoice';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('invoice_number', trans('accounting::accounting.fields.invoice_number'))
                ->optional()
                ->withHint(trans('accounting::accounting.hints.invoice_number')),

            Field::select('store_id', trans('accounting::accounting.fields.store'))
                ->setOptions($this->getStoreOptions())
                ->required(),

            Field::select('supplier_id', trans('accounting::accounting.fields.supplier'))
                ->setOptions($this->getSupplierOptions())
                ->advanced()
                ->required(),

            Field::select('purchase_order_id', trans('accounting::accounting.fields.purchase_order'))
                ->setOptions($this->getPurchaseOrderOptions())
                ->optional(),

            Field::date('invoice_date', trans('accounting::accounting.fields.invoice_date'))
                ->required(),

            Field::date('due_date', trans('accounting::accounting.fields.due_date'))
                ->required(),

            Field::select('currency_code', trans('accounting::accounting.fields.currency'))
                ->setOptions($this->getCurrencyOptions())
                ->required(),

            Field::price('subtotal', trans('accounting::accounting.fields.subtotal'))
                ->required(),

            Field::price('tax_amount', trans('accounting::accounting.fields.tax_amount'))
                ->withDefaultValue(0),

            Field::price('total_amount', trans('accounting::accounting.fields.total'))
                ->required(),

            Field::select('status', trans('accounting::accounting.fields.status'))
                ->setOptions($this->getStatusOptions())
                ->required(),

            Field::textarea('notes', trans('accounting::accounting.fields.notes'))
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('invoice_number')->withTitle(trans('accounting::accounting.fields.invoice_number'))->searchable()->sortable()->width('140px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.fields.supplier'))->width('150px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.fields.total'))->sortable()->width('130px'),
            Field::make('paid_amount')->withTitle(trans('accounting::accounting.fields.paid'))->sortable()->width('130px'),
            Field::date('invoice_date')->withTitle(trans('accounting::accounting.fields.invoice_date'))->sortable()->width('130px'),
            Field::date('due_date')->withTitle(trans('accounting::accounting.fields.due_date'))->sortable()->width('130px'),
            Field::make('status')->withTitle(trans('accounting::accounting.fields.status'))->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency_code' => ['required', 'string', 'max:3', 'exists:currencies,code'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,partial,paid,overdue,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function getStats(?QueryBuilder $query = null): array
    {
        $total = SupplierInvoice::count();
        $pending = SupplierInvoice::whereIn('status', ['pending', 'partial'])->count();
        $overdue = SupplierInvoice::where('status', 'overdue')->count();
        $totalAmount = SupplierInvoice::sum('total_amount');
        $paidAmount = SupplierInvoice::sum('paid_amount');

        return [
            StatCard::make(trans('accounting::accounting.stats.total_invoices'), (string)$total)->withColor('primary'),
            StatCard::make(trans('accounting::accounting.stats.pending_invoices'), (string)$pending)->withColor('warning'),
            StatCard::make(trans('accounting::accounting.stats.overdue_invoices'), (string)$overdue)->withColor('danger'),
            StatCard::make(trans('accounting::accounting.stats.total_payable'), number_format($totalAmount))->withColor('info')->withUnit(trans('accounting::accounting.currency_unit')),
            StatCard::make(trans('accounting::accounting.stats.total_paid'), number_format($paidAmount))->withColor('success')->withUnit(trans('accounting::accounting.currency_unit')),
        ];
    }

    protected function getStoreOptions(): array
    {
        return [1 => 'فروشگاه اصلی'];
    }

    protected function getSupplierOptions(): array
    {
        return \RMS\Accounting\Models\Supplier::pluck('name', 'id')->toArray();
    }

    protected function getPurchaseOrderOptions(): array
    {
        return ['' => trans('accounting::accounting.select_po')] + 
               \RMS\Accounting\Models\PurchaseOrder::pluck('po_number', 'id')->toArray();
    }

    protected function getCurrencyOptions(): array
    {
        return \RMS\Accounting\Models\Currency::where('active', true)->pluck('name', 'code')->toArray();
    }

    protected function getStatusOptions(): array
    {
        return [
            'pending' => trans('accounting::accounting.invoice_statuses.pending'),
            'partial' => trans('accounting::accounting.invoice_statuses.partial'),
            'paid' => trans('accounting::accounting.invoice_statuses.paid'),
            'overdue' => trans('accounting::accounting.invoice_statuses.overdue'),
            'cancelled' => trans('accounting::accounting.invoice_statuses.cancelled'),
        ];
    }
}
