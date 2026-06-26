<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class TaxRatesController extends AccountingAdminController implements
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

    /**
     * فرم اختصاصی (مثل ExpensesController) — قالب accounting::admin.tax_rates.form
     */
    public function create(Request $request)
    {
        $this->title(trans('accounting::accounting.tax_rates.pages.create_title'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view->setTpl('tax_rates.form')->withVariables([
            'isEdit' => false,
            'taxRate' => null,
            'taxTypeOptions' => $this->getTaxTypeOptions(),
        ]);

        return $this->view();
    }

    public function edit(Request $request, int|string $id)
    {
        $taxRate = TaxRate::query()->findOrFail($id);

        $this->title(trans('accounting::accounting.tax_rates.pages.edit_title'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view->setTpl('tax_rates.form')->withVariables([
            'isEdit' => true,
            'taxRate' => $taxRate,
            'taxTypeOptions' => $this->getTaxTypeOptions(),
        ]);

        return $this->view();
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.fields.tax_code'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.tax_code')),

            Field::string('name', trans('accounting::accounting.fields.tax_name'))
                ->required(),

            Field::select('tax_type', trans('accounting::accounting.fields.tax_type'))
                ->setOptions($this->getTaxTypeOptions())
                ->required(),

            Field::number('rate', trans('accounting::accounting.fields.tax_rate'))
                ->withDefaultValue(0)
                ->required()
                ->withHint(trans('accounting::accounting.hints.tax_rate')),

            Field::boolean('is_default', trans('accounting::accounting.fields.is_default'))
                ->withDefaultValue(false),

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
            Field::make('tax_type')
                ->withTitle(trans('accounting::accounting.fields.tax_type'))
                ->customMethod('renderTaxTypeLabel')
                ->width('140px'),
            Field::make('rate')->withTitle(trans('accounting::accounting.fields.tax_rate'))->sortable()->width('100px'),
            Field::boolean('is_default')->withTitle(trans('accounting::accounting.fields.is_default'))->width('100px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        $taxRateParam = request()->route('tax_rate');
        $ignoreId = $taxRateParam instanceof TaxRate ? $taxRateParam->getKey() : $taxRateParam;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tax_rates', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'tax_type' => ['required', 'in:vat,income_tax,withholding_tax,other'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    public function renderTaxTypeLabel($row): string
    {
        $key = $row->tax_type ?? '';
        $options = $this->getTaxTypeOptions();

        return e($options[$key] ?? $key);
    }

    public function boolFields(): array
    {
        return ['active', 'is_default'];
    }

    /**
     * برای `is_default` باید پیش‌فرض را در هر `tax_type` منحصر به فرد نگه داریم؛ بقیهٔ فیلدها مثل هسته.
     */
    public function toggleBoolField(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'field' => 'required|string|in:' . implode(',', $this->boolFields()),
        ]);

        if ($request->input('field') !== 'is_default') {
            return parent::toggleBoolField($request, $id);
        }

        try {
            $model = $this->model($id);
            if (! $model instanceof TaxRate) {
                throw new \InvalidArgumentException("Model with ID {$id} not found");
            }

            $newValue = ! $model->is_default;

            if ($newValue) {
                TaxRate::query()
                    ->where('tax_type', $model->tax_type)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $model->update(['is_default' => $newValue]);

            $message = trans('admin.field_updated_successfully', ['field' => 'is_default']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'value' => $newValue,
                    ],
                ]);
            }

            return back()->with('success', $message);
        } catch (Throwable $e) {
            Log::error('Tax rate is_default toggle failed', [
                'controller' => static::class,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = trans('admin.field_update_failed');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }

            return back()->withErrors($errorMessage);
        }
    }

    public function beforeAdd(Request &$request): void
    {
        if ($request->input('is_default')) {
            TaxRate::where('tax_type', $request->input('tax_type'))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        if ($request->input('is_default')) {
            TaxRate::where('id', '!=', $id)
                ->where('tax_type', $request->input('tax_type'))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    protected function getTaxTypeOptions(): array
    {
        return [
            TaxRate::TYPE_VAT => trans('accounting::accounting.tax_rate_types.vat'),
            TaxRate::TYPE_INCOME_TAX => trans('accounting::accounting.tax_rate_types.income_tax'),
            TaxRate::TYPE_WITHHOLDING_TAX => trans('accounting::accounting.tax_rate_types.withholding_tax'),
            TaxRate::TYPE_OTHER => trans('accounting::accounting.tax_rate_types.other'),
        ];
    }
}
