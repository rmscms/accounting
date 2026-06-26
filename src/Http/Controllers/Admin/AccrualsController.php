<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Accrual;
use RMS\Accounting\Services\AccrualService;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class AccrualsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected AccrualService $accrualService;

    public function __construct(Filesystem $filesystem, AccrualService $service)
    {
        parent::__construct($filesystem);
        $this->accrualService = $service;
    }

    public function table(): string
    {
        return 'accruals';
    }

    public function modelName(): string
    {
        return Accrual::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.accruals';
    }

    public function routeParameter(): string
    {
        return 'accrual';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('accrual_type', 'نوع تعهد')->setOptions([
                'accrued_revenue' => 'درآمد تعهدی',
                'accrued_expense' => 'هزینه تعهدی',
                'deferred_revenue' => 'درآمد موکول',
                'deferred_expense' => 'هزینه موکول',
            ])->required(),
            Field::number('amount', 'مبلغ')->required(),
            Field::select('account_id', 'حساب')->setOptions($this->getAccountOptions())->required(),
            Field::string('description', 'توضیحات')->required(),
            Field::date('accrual_date', 'تاریخ')->withDefaultValue(now()),
            Field::date('reversal_date', 'تاریخ برگشت')->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('accrual_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('accrual_type')->withTitle('نوع')->customMethod('renderType'),
            Field::make('amount')->withTitle('مبلغ')->customMethod('renderAmount')->sortable(),
            Field::boolean('is_reversed')->withTitle('برگشت خورده')->sortable(),
            Field::date('accrual_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return [
            'accrual_type' => ['required', 'in:accrued_revenue,accrued_expense,deferred_revenue,deferred_expense'],
            'amount' => ['required', 'numeric', 'min:0'],
            'account_id' => ['required', 'exists:accounts,id'],
            'description' => ['required', 'string'],
        ];
    }

    public function reverse($id)
    {
        $this->accrualService->reverseAccrual($id);
        return redirect()->back()->with('success', 'تعهد برگشت خورد');
    }

    protected function getAccountOptions(): array
    {
        return \RMS\Accounting\Models\Account::where('active', true)->get()->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"])->toArray();
    }

    public function renderAmount($row): string
    {
        return number_format($row->amount) . ' تومان';
    }

    public function renderType($row): string
    {
        $types = [
            'accrued_revenue' => '<span class="badge badge-success">درآمد تعهدی</span>',
            'accrued_expense' => '<span class="badge badge-danger">هزینه تعهدی</span>',
            'deferred_revenue' => '<span class="badge badge-info">درآمد موکول</span>',
            'deferred_expense' => '<span class="badge badge-warning">هزینه موکول</span>',
        ];
        return $types[$row->accrual_type] ?? $row->accrual_type;
    }
}
