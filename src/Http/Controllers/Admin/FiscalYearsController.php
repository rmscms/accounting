<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Services\FiscalYearCloseOrchestrationService;
use Illuminate\Http\Request;
use RMS\Core\Data\Action;
use RMS\Core\Data\Field;
use RMS\Core\View\HelperList\Generator;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class FiscalYearsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

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
        return 'accounting.fiscal_years';
    }

    public function routeParameter(): string
    {
        return 'fiscal_year';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('year_code', trans('accounting::accounting.fiscal_year.year_code'))->required(),
            Field::date('start_date', trans('accounting::accounting.fiscal_year.start_date'))->required(),
            Field::date('end_date', trans('accounting::accounting.fiscal_year.end_date'))->required(),
            Field::select('status', trans('accounting::accounting.fiscal_year.status'))
                ->setOptions([
                    'open' => trans('accounting::accounting.common.open'),
                    'locked' => 'قفل شده',
                    'closed' => trans('accounting::accounting.common.closed'),
                ])
                ->withDefaultValue('open'),
            Field::boolean('is_current', trans('accounting::accounting.fiscal_year.is_active'))->withDefaultValue(false),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('year_code')->withTitle(trans('accounting::accounting.fiscal_year.year_code'))->searchable()->sortable()->width('120px'),
            Field::date('start_date', trans('accounting::accounting.fiscal_year.start_date'))->sortable()->width('120px'),
            Field::date('end_date', trans('accounting::accounting.fiscal_year.end_date'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.fiscal_year.status'))->sortable()->width('100px'),
            Field::boolean('is_current')->withTitle(trans('accounting::accounting.fiscal_year.is_active'))->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.fiscal_year.status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'open' => trans('accounting::accounting.common.open'),
                    'locked' => 'قفل شده',
                    'closed' => trans('accounting::accounting.common.closed'),
                ]),
            Field::select('is_current', trans('accounting::accounting.fiscal_year.is_active'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.active'),
                    '0' => trans('accounting::accounting.common.inactive'),
                ]),
        ];
    }

    /**
     * بستن سال مالی (سریع — همان مسیر اداری ویزارد)
     */
    public function close(Request $request, int $id)
    {
        $fiscalYear = FiscalYear::findOrFail($id);

        if ($fiscalYear->status === 'closed') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        $userId = (int) auth()->guard('admin')->id();
        if ($userId === 0) {
            return redirect()->back()->with('error', trans('accounting::accounting.fiscal_year_close.wizard.errors.not_authenticated'));
        }

        try {
            app(FiscalYearCloseOrchestrationService::class)->closeAdministrative($id, $userId, null, false);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', trans('accounting::accounting.messages.fiscal_year_closed'));
    }

    protected function beforeGenerateList(Generator &$generator): void
    {
        parent::beforeGenerateList($generator);

        $routeName = $this->prefix_route.$this->baseRoute().'.close_wizard';
        $generator->addAction((new Action(
            trans('accounting::accounting.fiscal_year_close.wizard.list_action'),
            $routeName,
            config($this->theme.'.actions.edit'),
            'close-wizard btn-outline-warning'
        ))->withMethod('GET'));
    }

    public function boolFields(): array
    {
        return ['is_current'];
    }

    public function rules(): array
    {
        $id = request()->route('fiscal_year');

        return [
            'year_code' => ['required', 'string', 'max:20', 'unique:fiscal_years,year_code,' . ($id ?? 'NULL')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:open,locked,closed'],
            'is_current' => ['boolean'],
        ];
    }
}
