<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Services\CurrencyService;
use RMS\Accounting\Services\PartyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RMS\Core\Data\Field;
use RMS\Core\Requests\Store;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class CustomersController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'customers';
    }

    public function modelName(): string
    {
        return Customer::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.customers';
    }

    public function routeParameter(): string
    {
        return 'customer';
    }

    public function query(QueryBuilder $sql): void
    {
        $sql->leftJoin('customer_balances as cb', 'cb.customer_id', '=', 'a.id')
            ->addSelect(
                'a.*',
                DB::raw('COALESCE(cb.balance_irr, (COALESCE(cb.total_invoices, 0) - COALESCE(cb.total_payments, 0))) as balance_irr'),
                'cb.total_invoices',
                'cb.total_payments'
            )
            ->orderBy('a.created_at', 'desc');
    }

    public function getFieldsForm(): array
    {
        $currencyOptions = [];
        try {
            $currencyOptions = app(CurrencyService::class)
                ->getActiveCurrencies()
                ->mapWithKeys(fn ($c) => [(string) $c->code => (string) $c->code.' — '.(string) $c->name])
                ->all();
        } catch (\Throwable) {
            $currencyOptions = [];
        }

        return [
            Field::string('name', trans('accounting::accounting.customer.name'))
                ->required(),

            Field::select('type', trans('accounting::accounting.customer.type'))
                ->setOptions([
                    'regular' => trans('accounting::accounting.customer.type_regular'),
                    'vip' => trans('accounting::accounting.customer.type_vip'),
                    'occasional' => trans('accounting::accounting.customer.type_occasional'),
                ])
                ->withDefaultValue('regular')
                ->required(),

            Field::string('national_code', trans('accounting::accounting.customer.national_code'))
                ->optional(),

            Field::string('phone', trans('accounting::accounting.customer.phone'))
                ->optional(),

            Field::string('email', trans('accounting::accounting.customer.email'))
                ->optional(),

            Field::textarea('address', trans('accounting::accounting.customer.address'))
                ->optional(),

            Field::number('credit_limit', trans('accounting::accounting.customer.credit_limit'))
                ->withDefaultValue(0)
                ->required(),

            Field::select('default_currency_code', trans('accounting::accounting.customer.default_currency'))
                ->setOptions($currencyOptions)
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.customer.is_active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.customer.name'))->searchable()->sortable(),
            Field::make('type')->withTitle(trans('accounting::accounting.customer.type'))->customMethod('renderTypeBadge')->width('120px'),
            Field::make('phone')->withTitle(trans('accounting::accounting.customer.phone'))->searchable()->width('140px'),
            Field::make('default_currency_code')->withTitle(trans('accounting::accounting.customer.default_currency'))->width('100px'),
            Field::make('balance', 'COALESCE(cb.balance_irr, (COALESCE(cb.total_invoices, 0) - COALESCE(cb.total_payments, 0)))', true)
                ->withTitle(trans('accounting::accounting.customer.balance'))
                ->customMethod('renderBalance')
                ->width('150px'),
            Field::make('credit_limit')->withTitle(trans('accounting::accounting.customer.credit_limit'))->customMethod('renderCreditLimit')->width('140px'),
            Field::make('party_roles', 'a.id')->withTitle('نقش')->customMethod('renderPartyRoles')->width('150px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.common.status'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    /**
     * Store customer with party support
     */
    public function store(Store $request): RedirectResponse
    {
        $partyService = app(PartyService::class);
        
        // دریافت rules و تبدیل به string format
        $rulesArray = $this->rules();
        $rules = [];
        foreach ($rulesArray as $field => $rule) {
            if (is_array($rule)) {
                $rules[$field] = implode('|', $rule);
            } else {
                $rules[$field] = $rule;
            }
        }
        $rules['party_id'] = 'nullable|exists:parties,id';
        $validated = $request->validate($rules);

        $customer = $partyService->createOrLinkCustomer($validated);

        $response = redirect()->route($this->accountingNamedRoute('index'))
            ->with('success', trans('accounting::accounting.customer.created'));

        return $this->redirectAfterCustomerStored($customer, $response);
    }

    protected function redirectAfterCustomerStored(Customer $customer, RedirectResponse $response): RedirectResponse
    {
        return $response;
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof Customer) {
            return;
        }

        // برای ویرایش نیز وجود party/account تفصیلی مشتری را تضمین می‌کنیم.
        app(PartyService::class)->ensurePartyForCustomer($model->fresh());
    }

    public function rules(): array
    {
        $id = request()->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:regular,vip,occasional'],
            'national_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'default_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'active' => ['boolean'],
        ];
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() !== 'customers') {
            return;
        }

        // credit_limit in this project treats 0 as "unlimited".
        // When amount formatter sends empty string, normalize to 0 to avoid false required errors.
        $this->mergeParsedDecimalFields($request, ['credit_limit'], 'credit_limit');
        if (! $request->filled('credit_limit')) {
            $request->merge(['credit_limit' => '0']);
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?\Illuminate\Database\Eloquent\Model $model): array
    {
        if ($this->structuredAccountingFormSlug() !== 'customers') {
            return [];
        }

        $convertUrl = null;
        $canConvert = false;
        if ($isEdit && $model instanceof Customer) {
            $hasSupplier = false;
            $partyId = (int) ($model->party_id ?? 0);
            if ($partyId > 0) {
                $hasSupplier = Supplier::query()->where('party_id', $partyId)->exists();
            }
            if (! $hasSupplier) {
                $canConvert = true;
                $convertUrl = route('admin.accounting.suppliers.create', [
                    'linked_customer_id' => (string) $model->id,
                ]);
            }
        }

        return [
            'customerCanConvertToSupplier' => $canConvert,
            'customerConvertToSupplierUrl' => $convertUrl,
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    /**
     * Render customer type as badge
     */
    public function renderTypeBadge($row): string
    {
        $badges = [
            'vip' => '<span class="badge badge-success">VIP</span>',
            'regular' => '<span class="badge badge-primary">' . trans('accounting::accounting.customer.type_regular') . '</span>',
            'occasional' => '<span class="badge badge-secondary">' . trans('accounting::accounting.customer.type_occasional') . '</span>',
        ];

        return $badges[$row->type] ?? $row->type;
    }

    /**
     * Render balance with color coding
     */
    public function renderBalance($row): string
    {
        $balance = (float) (
            $row->balance
            ?? $row->balance_irr
            ?? ((float) ($row->total_invoices ?? 0) - (float) ($row->total_payments ?? 0))
        );
        
        if ($balance > 0) {
            // مشتری بدهکار است (قرمز)
            return '<span class="text-danger font-weight-bold">' . number_format($balance) . ' تومان</span>';
        } elseif ($balance < 0) {
            // مشتری بستانکار است (سبز)
            return '<span class="text-success font-weight-bold">' . number_format(abs($balance)) . ' تومان</span>';
        }
        
        return '<span class="text-muted">0 تومان</span>';
    }

    /**
     * Render credit limit formatted
     */
    public function renderCreditLimit($row): string
    {
        if (empty($row->credit_limit) || $row->credit_limit == 0) {
            return '<span class="text-muted">∞</span>';
        }
        
        return '<span class="text-info">' . number_format($row->credit_limit) . ' تومان</span>';
    }

    /**
     * Render party roles badge for list.
     */
    public function renderPartyRoles($row): string
    {
        $customer = Customer::with('party.supplier')->find($row->id);
        if (!$customer || !$customer->party) {
            return '<span class="badge bg-secondary">مشتری</span>';
        }
        $party = $customer->party;
        if (method_exists($party, 'isBoth') && $party->isBoth()) {
            return '<span class="badge bg-primary">مشتری + تامین‌کننده</span>';
        }
        return '<span class="badge bg-secondary">مشتری</span>';
    }
}
