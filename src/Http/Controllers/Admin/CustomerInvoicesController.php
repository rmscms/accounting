<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\CustomerInvoice;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerInvoicesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'customer_invoices';
    }

    public function modelName(): string
    {
        return CustomerInvoice::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.customer-invoices';
    }

    public function routeParameter(): string
    {
        return 'customer_invoice';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('invoice_number', trans('accounting::accounting.invoice.invoice_number'))->required(),
            Field::date('invoice_date', trans('accounting::accounting.invoice.invoice_date'))->required(),
            Field::number('customer_id', trans('accounting::accounting.invoice.customer_id'))->required(),
            Field::number('subtotal', trans('accounting::accounting.invoice.subtotal'))->required(),
            Field::number('tax_amount', trans('accounting::accounting.invoice.tax_amount'))->withDefaultValue(0),
            Field::number('discount_amount', trans('accounting::accounting.invoice.discount_amount'))->withDefaultValue(0),
            Field::number('total_amount', trans('accounting::accounting.invoice.total_amount'))->required(),
            Field::select('status', trans('accounting::accounting.invoice.status'))
                ->options([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'issued' => trans('accounting::accounting.statuses.issued'),
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
            Field::make('invoice_number')->withTitle(trans('accounting::accounting.invoice.invoice_number'))->searchable()->sortable()->width('150px'),
            Field::make('invoice_date')->withTitle(trans('accounting::accounting.invoice.invoice_date'))->sortable()->width('120px'),
            Field::make('customer_id')->withTitle(trans('accounting::accounting.invoice.customer_id'))->sortable()->width('100px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.invoice.total_amount'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.invoice.status'))->sortable()->width('100px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.invoice.status'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'issued' => trans('accounting::accounting.statuses.issued'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ]),
        ];
    }
}
