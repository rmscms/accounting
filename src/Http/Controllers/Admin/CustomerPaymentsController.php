<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\CustomerPayment;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerPaymentsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'customer_payments';
    }

    public function modelName(): string
    {
        return CustomerPayment::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.customer-payments';
    }

    public function routeParameter(): string
    {
        return 'customer_payment';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('payment_number', trans('accounting::accounting.payment.payment_number'))->required(),
            Field::date('payment_date', trans('accounting::accounting.payment.payment_date'))->required(),
            Field::number('customer_id', trans('accounting::accounting.payment.customer_id'))->required(),
            Field::number('amount', trans('accounting::accounting.payment.amount'))->required(),
            Field::select('payment_method', trans('accounting::accounting.payment.payment_method'))
                ->options([
                    'cash' => trans('accounting::accounting.payment_methods.cash'),
                    'cheque' => trans('accounting::accounting.payment_methods.cheque'),
                    'pos' => trans('accounting::accounting.payment_methods.pos'),
                    'card_to_card' => trans('accounting::accounting.payment_methods.card_to_card'),
                    'online' => trans('accounting::accounting.payment_methods.online'),
                    'wallet' => trans('accounting::accounting.payment_methods.wallet'),
                ])
                ->required(),
            Field::textarea('notes', trans('accounting::accounting.payment.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('payment_number')->withTitle(trans('accounting::accounting.payment.payment_number'))->searchable()->sortable()->width('150px'),
            Field::make('payment_date')->withTitle(trans('accounting::accounting.payment.payment_date'))->sortable()->width('120px'),
            Field::make('customer_id')->withTitle(trans('accounting::accounting.payment.customer_id'))->sortable()->width('100px'),
            Field::make('amount')->withTitle(trans('accounting::accounting.payment.amount'))->sortable()->width('120px'),
            Field::make('payment_method')->withTitle(trans('accounting::accounting.payment.payment_method'))->sortable()->width('120px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('payment_method', trans('accounting::accounting.payment.payment_method'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'cash' => trans('accounting::accounting.payment_methods.cash'),
                    'cheque' => trans('accounting::accounting.payment_methods.cheque'),
                    'pos' => trans('accounting::accounting.payment_methods.pos'),
                    'card_to_card' => trans('accounting::accounting.payment_methods.card_to_card'),
                    'online' => trans('accounting::accounting.payment_methods.online'),
                    'wallet' => trans('accounting::accounting.payment_methods.wallet'),
                ]),
        ];
    }
}
