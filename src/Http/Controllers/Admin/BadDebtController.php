<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ManagesCustomExpenseForm;
use RMS\Accounting\Models\BadDebtProvision;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Services\BadDebtService;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Models\Setting;
use RMS\Core\Requests\Store;

class BadDebtController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use ManagesCustomExpenseForm;

    protected BadDebtService $badDebtService;

    public function __construct(Filesystem $filesystem, BadDebtService $service)
    {
        parent::__construct($filesystem);
        $this->badDebtService = $service;
        $this->addAmountFields(['provision_amount']);
    }

    public function table(): string
    {
        return 'bad_debt_provisions';
    }

    public function modelName(): string
    {
        return BadDebtProvision::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.bad-debt';
    }

    public function routeParameter(): string
    {
        return 'bad_debt';
    }

    public function create(Request $request)
    {
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter']
        );

        $this->view
            ->setTpl('bad_debt.form')
            ->withPlugins($plugins)
            ->withVariables([
                'isEdit' => false,
                'provision' => null,
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        $this->title(trans('accounting::accounting.bad_debt_form.title_create'));

        return $this->view();
    }

    public function edit(Request $request, int|string $id)
    {
        $provision = BadDebtProvision::with('customer')->findOrFail($id);

        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter']
        );

        $this->view
            ->setTpl('bad_debt.form')
            ->withPlugins($plugins)
            ->withVariables([
                'isEdit' => true,
                'provision' => $provision,
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        $this->title(trans('accounting::accounting.bad_debt_form.title_edit'));

        return $this->view();
    }

    public function store(Store $request): RedirectResponse
    {
        $validated = $request->validated();

        $amount = $this->parseDecimalAmount($validated['provision_amount']);
        if ($amount === null || $amount < 0) {
            throw ValidationException::withMessages([
                'provision_amount' => [trans('accounting::accounting.bad_debt_form.invalid_amount')],
            ]);
        }

        $dec = $this->resolveAccountingAmountDecimalPlaces();
        $amount = round($amount, $dec);
        $normalized = number_format($amount, $dec, '.', '');

        $provisionDate = $this->normalizePostedAccountingDate($request, 'provision_date');

        $this->badDebtService->recordProvision([
            'customer_id' => $validated['customer_id'] ?? null,
            'provision_amount' => $normalized,
            'provision_date' => $provisionDate,
            'calculation_method' => $validated['calculation_method'],
            'percentage_used' => $validated['percentage_used'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.accounting.bad-debt.index')
            ->with('success', trans('accounting::accounting.bad_debt_form.saved'));
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $provision = BadDebtProvision::findOrFail($id);

        $validated = $request->validate($this->badDebtProvisionUpdateRules());

        $provision->customer_id = $validated['customer_id'] ?? null;
        $provision->calculation_method = $validated['calculation_method'];
        $provision->percentage_used = $validated['percentage_used'] ?? null;
        $provision->notes = $validated['notes'] ?? null;
        $provision->provision_date = $this->normalizePostedAccountingDate($request, 'provision_date');
        $provision->save();

        return redirect()
            ->route('admin.accounting.bad-debt.index')
            ->with('success', trans('accounting::accounting.bad_debt_form.updated'));
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['results' => []]);
        }

        $escaped = addcslashes($q, '%_\\');
        $term = '%' . $escaped . '%';

        $rows = Customer::query()
            ->where('active', true)
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);

        $results = $rows->map(static fn (Customer $c): array => [
            'id' => (string) $c->id,
            'text' => (string) $c->name,
            'entity_type' => 'customer',
            'entity_type_label' => (string) trans('accounting::accounting.supplier.party_badge_customer'),
        ])->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function badDebtProvisionStoreRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'provision_amount' => ['required', 'string', 'max:64'],
            'calculation_method' => ['required', 'in:percentage_sales,aging_analysis,specific_identification'],
            'percentage_used' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:calculation_method,percentage_sales'],
            'provision_date' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function badDebtProvisionUpdateRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'calculation_method' => ['required', 'in:percentage_sales,aging_analysis,specific_identification'],
            'percentage_used' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:calculation_method,percentage_sales'],
            'provision_date' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('customer_id', 'مشتری')->setOptions([])->optional(),
            Field::text('provision_amount', 'مبلغ ذخیره')->required(),
            Field::select('calculation_method', 'روش محاسبه')->setOptions([
                'percentage_sales' => 'درصد فروش',
                'aging_analysis' => 'تجزیه سنی',
                'specific_identification' => 'شناسایی خاص',
            ])->required(),
            Field::number('percentage_used', 'درصد استفاده شده')->optional(),
            Field::date('provision_date', 'تاریخ')->withDefaultValue(now()),
            Field::textarea('notes', 'یادداشت')->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('provision_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('customer.name')->withTitle('مشتری')->searchable(),
            Field::make('provision_amount')->withTitle('مبلغ')->customMethod('renderAmount')->sortable(),
            Field::make('calculation_method')->withTitle('روش')->customMethod('renderMethod'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus'),
            Field::date('provision_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return $this->badDebtProvisionStoreRules();
    }

    public function writeoff(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'writeoff_amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
        ]);

        $this->badDebtService->writeOffBadDebt($validated);

        return redirect()->back()->with('success', 'مطالبات مشکوک حذف شد');
    }

    public function renderAmount($row): string
    {
        $code = Currency::resolveBaseCurrencyCode('IRR');

        $dec = min(4, max(0, (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0))));

        return number_format((float) $row->provision_amount, $dec) . ' ' . $code;
    }

    public function renderMethod($row): string
    {
        $methods = [
            'percentage_sales' => '<span class="badge badge-info">درصد فروش</span>',
            'aging_analysis' => '<span class="badge badge-primary">تجزیه سنی</span>',
            'specific_identification' => '<span class="badge badge-secondary">شناسایی خاص</span>',
        ];

        return $methods[$row->calculation_method] ?? $row->calculation_method;
    }

    public function renderStatus($row): string
    {
        $badges = [
            'active' => '<span class="badge badge-success">فعال</span>',
            'written_off' => '<span class="badge badge-danger">حذف شده</span>',
            'recovered' => '<span class="badge badge-info">بازیافت شده</span>',
            'cancelled' => '<span class="badge badge-secondary">لغو شده</span>',
        ];

        return $badges[$row->status] ?? $row->status;
    }
}
