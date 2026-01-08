<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Supplier;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class SuppliersController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'suppliers';
    }

    public function modelName(): string
    {
        return Supplier::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.suppliers';
    }

    public function routeParameter(): string
    {
        return 'supplier';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.supplier.name'))->required(),
            Field::string('code', trans('accounting::accounting.supplier.code'))->optional(),
            Field::string('tax_number', trans('accounting::accounting.supplier.tax_number'))->optional(),
            Field::string('phone', trans('accounting::accounting.supplier.phone'))->optional(),
            Field::string('email', trans('accounting::accounting.supplier.email'))->optional(),
            Field::textarea('address', trans('accounting::accounting.supplier.address'))->optional(),
            Field::boolean('active', trans('accounting::accounting.supplier.active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.supplier.name'))->searchable()->sortable(),
            Field::make('code')->withTitle(trans('accounting::accounting.supplier.code'))->searchable()->sortable()->width('120px'),
            Field::make('phone')->withTitle(trans('accounting::accounting.supplier.phone'))->searchable()->width('150px'),
            Field::make('email')->withTitle(trans('accounting::accounting.supplier.email'))->searchable(),
            Field::make('active')->withTitle(trans('accounting::accounting.supplier.active'))->boolean()->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('active', trans('accounting::accounting.supplier.active'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.active'),
                    '0' => trans('accounting::accounting.common.inactive'),
                ]),
        ];
    }
}
