<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\PurchaseOrder;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class PurchaseOrdersController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
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
        return 'admin.accounting.purchase-orders';
    }

    public function routeParameter(): string
    {
        return 'purchase_order';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('po_number', trans('accounting::accounting.purchase_order.po_number'))->required(),
            Field::date('order_date', trans('accounting::accounting.purchase_order.order_date'))->required(),
            Field::number('supplier_id', trans('accounting::accounting.purchase_order.supplier_id'))->required(),
            Field::number('total_amount', trans('accounting::accounting.purchase_order.total_amount'))->required(),
            Field::select('status', trans('accounting::accounting.purchase_order.status'))
                ->options([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'received' => trans('accounting::accounting.statuses.received'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ])
                ->withDefaultValue('draft')
                ->required(),
            Field::textarea('notes', trans('accounting::accounting.purchase_order.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('po_number')->withTitle(trans('accounting::accounting.purchase_order.po_number'))->searchable()->sortable()->width('150px'),
            Field::make('order_date')->withTitle(trans('accounting::accounting.purchase_order.order_date'))->sortable()->width('120px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.purchase_order.supplier_id'))->sortable()->width('100px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.purchase_order.total_amount'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.purchase_order.status'))->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.purchase_order.status'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'received' => trans('accounting::accounting.statuses.received'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ]),
        ];
    }

    /**
     * تایید سفارش خرید
     */
    public function confirm(Request $request, int $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.po_not_draft'));
        }

        $purchaseOrder->status = 'confirmed';
        $purchaseOrder->confirmed_by = auth()->id();
        $purchaseOrder->confirmed_at = now();
        $purchaseOrder->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.po_confirmed'));
    }
}
