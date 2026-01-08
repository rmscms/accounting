<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\SupplierInvoice;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class SupplierInvoicesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
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
        return 'admin.accounting.supplier-invoices';
    }

    public function routeParameter(): string
    {
        return 'supplier_invoice';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('invoice_number', trans('accounting::accounting.supplier_invoice.invoice_number'))->required(),
            Field::date('invoice_date', trans('accounting::accounting.supplier_invoice.invoice_date'))->required(),
            Field::number('supplier_id', trans('accounting::accounting.supplier_invoice.supplier_id'))->required(),
            Field::number('total_amount', trans('accounting::accounting.supplier_invoice.total_amount'))->required(),
            Field::select('status', trans('accounting::accounting.supplier_invoice.status'))
                ->options([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ])
                ->withDefaultValue('draft')
                ->required(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('invoice_number')->withTitle(trans('accounting::accounting.supplier_invoice.invoice_number'))->searchable()->sortable()->width('150px'),
            Field::make('invoice_date')->withTitle(trans('accounting::accounting.supplier_invoice.invoice_date'))->sortable()->width('120px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.supplier_invoice.supplier_id'))->sortable()->width('100px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.supplier_invoice.total_amount'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.supplier_invoice.status'))->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.supplier_invoice.status'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ]),
        ];
    }
}
