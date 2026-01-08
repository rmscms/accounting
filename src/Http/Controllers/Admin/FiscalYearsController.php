<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\FiscalYear;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class FiscalYearsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'fiscal_years';
    }

    public function modelName(): string
    {
        return FiscalYear::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.fiscal-years';
    }

    public function routeParameter(): string
    {
        return 'fiscal_year';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.fiscal_year.name'))->required(),
            Field::date('start_date', trans('accounting::accounting.fiscal_year.start_date'))->required(),
            Field::date('end_date', trans('accounting::accounting.fiscal_year.end_date'))->required(),
            Field::boolean('is_active', trans('accounting::accounting.fiscal_year.is_active'))->withDefaultValue(true),
            Field::boolean('is_closed', trans('accounting::accounting.fiscal_year.is_closed'))->withDefaultValue(false),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fiscal_year.name'))->searchable()->sortable(),
            Field::make('start_date')->withTitle(trans('accounting::accounting.fiscal_year.start_date'))->sortable()->width('120px'),
            Field::make('end_date')->withTitle(trans('accounting::accounting.fiscal_year.end_date'))->sortable()->width('120px'),
            Field::make('is_active')->withTitle(trans('accounting::accounting.fiscal_year.is_active'))->boolean()->sortable()->width('100px'),
            Field::make('is_closed')->withTitle(trans('accounting::accounting.fiscal_year.is_closed'))->boolean()->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('is_active', trans('accounting::accounting.fiscal_year.is_active'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.active'),
                    '0' => trans('accounting::accounting.common.inactive'),
                ]),
            Field::select('is_closed', trans('accounting::accounting.fiscal_year.is_closed'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.closed'),
                    '0' => trans('accounting::accounting.common.open'),
                ]),
        ];
    }

    /**
     * بستن سال مالی
     */
    public function close(Request $request, int $id)
    {
        $fiscalYear = FiscalYear::findOrFail($id);
        
        if ($fiscalYear->is_closed) {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        // TODO: استفاده از FiscalYearService برای بستن سال مالی
        
        return redirect()->back()->with('success', trans('accounting::accounting.messages.fiscal_year_closed'));
    }
}
