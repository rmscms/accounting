<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\TaxRate;
use Illuminate\Http\Request;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class TaxRatesController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'tax_rates';
    }

    public function modelName(): string
    {
        return TaxRate::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.tax-rates';
    }

    public function routeParameter(): string
    {
        return 'tax_rate';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.fields.tax_code'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.tax_code')),

            Field::string('name', trans('accounting::accounting.fields.tax_name'))
                ->required(),

            Field::select('type', trans('accounting::accounting.fields.tax_type'))
                ->setOptions($this->getTaxTypeOptions())
                ->required(),

            Field::number('rate', trans('accounting::accounting.fields.tax_rate'))
                ->withDefaultValue(0)
                ->required()
                ->withHint(trans('accounting::accounting.hints.tax_rate')),

            Field::boolean('is_default', trans('accounting::accounting.fields.is_default'))
                ->withDefaultValue(false),

            Field::textarea('description', trans('accounting::accounting.fields.description'))
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('accounting::accounting.fields.tax_code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.tax_name'))->searchable()->sortable(),
            Field::make('type')->withTitle(trans('accounting::accounting.fields.tax_type'))->width('100px'),
            Field::make('rate')->withTitle(trans('accounting::accounting.fields.tax_rate'))->sortable()->width('100px'),
            Field::boolean('is_default')->withTitle(trans('accounting::accounting.fields.is_default'))->width('100px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('tax_rate');

        return [
            'code' => ['required', 'string', 'max:50', 'unique:tax_rates,code,' . ($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:vat,sales_tax,purchase_tax,withholding_tax'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function beforeAdd(Request &$request): void
    {
        if ($request->input('is_default')) {
            TaxRate::where('type', $request->input('type'))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        if ($request->input('is_default')) {
            TaxRate::where('id', '!=', $id)
                ->where('type', $request->input('type'))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    protected function getTaxTypeOptions(): array
    {
        return [
            'vat' => trans('accounting::accounting.tax_types.vat'),
            'sales_tax' => trans('accounting::accounting.tax_types.sales_tax'),
            'purchase_tax' => trans('accounting::accounting.tax_types.purchase_tax'),
            'withholding_tax' => trans('accounting::accounting.tax_types.withholding_tax'),
        ];
    }
}
