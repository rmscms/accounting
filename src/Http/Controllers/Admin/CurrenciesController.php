<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class CurrenciesController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'currencies';
    }

    public function modelName(): string
    {
        return Currency::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.currencies';
    }

    public function routeParameter(): string
    {
        return 'currency';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.fields.currency_code'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.currency_code')),

            Field::string('name', trans('accounting::accounting.fields.currency_name'))
                ->required(),

            Field::string('symbol', trans('accounting::accounting.fields.currency_symbol'))
                ->optional(),

            Field::number('exchange_rate', trans('accounting::accounting.fields.exchange_rate'))
                ->withDefaultValue(1)
                ->required()
                ->withHint(trans('accounting::accounting.hints.exchange_rate')),

            Field::boolean('is_base', trans('accounting::accounting.fields.is_base_currency'))
                ->withDefaultValue(false),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('accounting::accounting.fields.currency_code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.currency_name'))->searchable()->sortable(),
            Field::make('symbol')->withTitle(trans('accounting::accounting.fields.currency_symbol'))->width('100px'),
            Field::make('exchange_rate')->withTitle(trans('accounting::accounting.fields.exchange_rate'))->sortable()->width('140px'),
            Field::boolean('is_base')->withTitle(trans('accounting::accounting.fields.is_base_currency'))->width('120px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
            Field::date('updated_at')->withTitle(trans('accounting::accounting.fields.last_updated'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('currency');

        return [
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code,' . ($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_base' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function beforeAdd(Request &$request): void
    {
        if ($request->input('is_base')) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        if ($request->input('is_base')) {
            Currency::where('id', '!=', $id)->where('is_base', true)->update(['is_base' => false]);
        }
    }
}
