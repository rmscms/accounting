<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\PaymentMethod;
use Illuminate\Http\Request;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class PaymentMethodsController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'payment_methods';
    }

    public function modelName(): string
    {
        return PaymentMethod::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.payment-methods';
    }

    public function routeParameter(): string
    {
        return 'payment_method';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.fields.payment_method_code'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.payment_method_code')),

            Field::string('name', trans('accounting::accounting.fields.payment_method_name'))
                ->required(),

            Field::select('type', trans('accounting::accounting.fields.payment_type'))
                ->setOptions($this->getPaymentTypeOptions())
                ->required(),

            Field::textarea('description', trans('accounting::accounting.fields.description'))
                ->optional(),

            Field::number('sort', trans('accounting::accounting.fields.sort'))
                ->withDefaultValue(0)
                ->required(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('accounting::accounting.fields.payment_method_code'))->searchable()->sortable()->width('140px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.payment_method_name'))->searchable()->sortable(),
            Field::make('type')->withTitle(trans('accounting::accounting.fields.payment_type'))->sortable()->width('120px'),
            Field::number('sort')->withTitle(trans('accounting::accounting.fields.sort'))->width('90px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('payment_method');

        return [
            'code' => ['required', 'string', 'max:50', 'unique:payment_methods,code,' . ($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,pos,online,cheque,card_to_card,wallet'],
            'description' => ['nullable', 'string'],
            'sort' => ['required', 'integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    protected function getPaymentTypeOptions(): array
    {
        return [
            'cash' => trans('accounting::accounting.payment_types.cash'),
            'pos' => trans('accounting::accounting.payment_types.pos'),
            'online' => trans('accounting::accounting.payment_types.online'),
            'cheque' => trans('accounting::accounting.payment_types.cheque'),
            'card_to_card' => trans('accounting::accounting.payment_types.card_to_card'),
            'wallet' => trans('accounting::accounting.payment_types.wallet'),
        ];
    }
}
