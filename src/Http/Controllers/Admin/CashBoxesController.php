<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Services\ReportService;
use RMS\Accounting\Services\TreasurySubAccountProvisioningService;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class CashBoxesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'cash_boxes';
    }

    public function modelName(): string
    {
        return CashBox::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.cashboxes';
    }

    public function routeParameter(): string
    {
        return 'cashbox';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.fields.cashbox_name'))
                ->required(),

            Field::string('location', trans('accounting::accounting.cash_box.location'))
                ->optional(),

            Field::boolean('auto_create_account', trans('accounting::accounting.treasury_sub_accounts.toggle_label'))
                ->withDefaultValue(true)
                ->virtual(),

            Field::select('account_id', trans('accounting::accounting.bank.account_id'))
                ->setOptions($this->getCashBoxAccountOptions())
                ->optional(),

            Field::select('currency_code', trans('accounting::accounting.cash_box.currency_code'))
                ->setOptions($this->getCurrencyOptions())
                ->withDefaultValue(Currency::resolveBaseCurrencyCode('IRT'))
                ->required(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.cashbox_name'))->searchable()->sortable(),
            Field::make('location')->withTitle(trans('accounting::accounting.cash_box.location'))->width('150px'),
            Field::make('account_id')->withTitle(trans('accounting::accounting.bank.account_id'))->width('100px'),
            Field::make('balance')->withTitle(trans('accounting::accounting.fields.balance'))->sortable()->width('140px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('accounting::accounting.fields.created_at'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'auto_create_account' => ['nullable', 'boolean'],
            'account_id' => [
                'required_if:auto_create_account,0',
                'nullable',
                'integer',
                'exists:accounts,id',
            ],
            'currency_code' => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'active' => ['boolean'],
        ];
    }

    protected function beforeAdd(Request &$request): void
    {
        parent::beforeAdd($request);
        $this->applyAutoProvisionedAccount($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        parent::beforeUpdate($request, $id);
        $this->applyAutoProvisionedAccount($request);
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        $this->view->withJs('vendor/accounting/admin/js/treasury-auto-account-toggle.js', true);
    }

    protected function applyAutoProvisionedAccount(Request &$request): void
    {
        $autoCreateEnabled = $this->resolveAutoCreateAccountEnabled($request, true);
        $requestedAccountId = (int) $request->input('account_id', 0);
        $requestedAccountExists = $requestedAccountId > 0
            && Account::query()->whereKey($requestedAccountId)->exists();

        if (! $autoCreateEnabled) {
            if (! $requestedAccountExists) {
                throw ValidationException::withMessages([
                    'account_id' => [trans('validation.exists', ['attribute' => trans('accounting::accounting.bank.account_id')])],
                ]);
            }

            return;
        }

        try {
            $account = app(TreasurySubAccountProvisioningService::class)->provisionFor(
                TreasurySubAccountProvisioningService::TYPE_CASHBOX,
                (string) $request->input('name', '')
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'auto_create_account' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'auto_create_account' => [trans('accounting::accounting.treasury_sub_accounts.errors.provision_failed')],
            ]);
        }

        $resolvedId = (int) Account::query()->whereKey((int) $account->id)->value('id');
        if ($resolvedId < 1) {
            throw ValidationException::withMessages([
                'auto_create_account' => [trans('accounting::accounting.treasury_sub_accounts.errors.provision_failed')],
            ]);
        }

        $request->merge(['account_id' => $resolvedId]);
    }

    protected function resolveAutoCreateAccountEnabled(?Request $request = null, bool $default = true): bool
    {
        $request = $request instanceof Request ? $request : request();
        $value = $request?->input('auto_create_account');

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function show(Request $request, int|string $id)
    {
        $cashbox = CashBox::query()->with('account')->findOrFail((int) $id);
        $statement = app(ReportService::class)->getCashboxStatementShowData((int) $cashbox->id, $request->all());

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->title(trans('accounting::accounting.reports.treasury_statement.cashbox_title', [
            'name' => (string) $cashbox->name,
        ]));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view
            ->setTheme('admin')
            ->setTpl('cashboxes.show')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/accounting-date-ui.js', true)
            ->withVariables([
                'cashbox' => $cashbox,
                'statement' => $statement,
            ]);

        return $this->view();
    }

    /**
     * @return array<int|string, string>
     */
    protected function getCashBoxAccountOptions(): array
    {
        return Account::query()
            ->where('active', true)
            ->orderBy('code')
            ->limit(500)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Account $a) => [$a->id => $a->code.' — '.$a->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function getCurrencyOptions(): array
    {
        return Currency::query()
            ->where('active', true)
            ->orderBy('code')
            ->pluck('name', 'code')
            ->all();
    }
}
