<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Services\PurchaseOrderService;
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

class PurchaseOrdersController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    HasStats
{
    protected PurchaseOrderService $poService;

    public function __construct(PurchaseOrderService $poService)
    {
        parent::__construct();
        $this->poService = $poService;
    }

    public function table(): string
    {
        return 'purchase_orders';
    }

    public function modelName(): string
    {
        return PurchaseOrder::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.purchase-orders';
    }

    public function routeParameter(): string
    {
        return 'purchase_order';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('po_number', trans('accounting::accounting.fields.po_number'))
                ->optional()
                ->withHint(trans('accounting::accounting.hints.po_number')),

            Field::select('store_id', trans('accounting::accounting.fields.store'))
                ->setOptions($this->getStoreOptions())
                ->required(),

            Field::select('supplier_id', trans('accounting::accounting.fields.supplier'))
                ->setOptions($this->getSupplierOptions())
                ->advanced()
                ->required(),

            Field::date('po_date', trans('accounting::accounting.fields.po_date'))
                ->required(),

            Field::date('expected_delivery_date', trans('accounting::accounting.fields.expected_delivery'))
                ->optional(),

            Field::select('currency_code', trans('accounting::accounting.fields.currency'))
                ->setOptions($this->getCurrencyOptions())
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
            Field::make('po_number')->withTitle(trans('accounting::accounting.fields.po_number'))->searchable()->sortable()->width('140px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.fields.supplier'))->width('150px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.fields.total'))->sortable()->width('130px'),
            Field::date('po_date')->withTitle(trans('accounting::accounting.fields.po_date'))->sortable()->width('130px'),
            Field::date('expected_delivery_date')->withTitle(trans('accounting::accounting.fields.expected_delivery'))->sortable()->width('140px'),
            Field::make('status')->withTitle(trans('accounting::accounting.fields.status'))->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'po_number' => ['nullable', 'string', 'max:50'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'currency_code' => ['required', 'string', 'max:3', 'exists:currencies,code'],
            'status' => ['required', 'in:draft,sent,confirmed,partially_received,received,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function getStats(?QueryBuilder $query = null): array
    {
        $total = PurchaseOrder::count();
        $pending = PurchaseOrder::whereIn('status', ['sent', 'confirmed'])->count();
        $received = PurchaseOrder::where('status', 'received')->count();

        return [
            StatCard::make(trans('accounting::accounting.stats.total_pos'), (string)$total)->withColor('primary'),
            StatCard::make(trans('accounting::accounting.stats.pending_pos'), (string)$pending)->withColor('warning'),
            StatCard::make(trans('accounting::accounting.stats.received_pos'), (string)$received)->withColor('success'),
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

    protected function getCurrencyOptions(): array
    {
        return \RMS\Accounting\Models\Currency::where('active', true)->pluck('name', 'code')->toArray();
    }

    protected function getStatusOptions(): array
    {
        return [
            'draft' => trans('accounting::accounting.po_statuses.draft'),
            'sent' => trans('accounting::accounting.po_statuses.sent'),
            'confirmed' => trans('accounting::accounting.po_statuses.confirmed'),
            'partially_received' => trans('accounting::accounting.po_statuses.partially_received'),
            'received' => trans('accounting::accounting.po_statuses.received'),
            'cancelled' => trans('accounting::accounting.po_statuses.cancelled'),
        ];
    }
}
