<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Services\ReportService;
use RMS\Accounting\Services\TreasurySubAccountProvisioningService;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class BanksController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'banks';
    }

    public function modelName(): string
    {
        return Bank::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.banks';
    }

    public function routeParameter(): string
    {
        return 'bank';
    }

    /**
     * فرم پویا (در صورت استفاده مجدد) — بدون پر کردن کشویی حساب‌ها؛ انتخاب حساب در create/edit اختصاصی است.
     */
    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.bank.name'))
                ->required(),

            Field::string('short_name', trans('accounting::accounting.bank.short_name'))
                ->optional(),

            Field::string('branch_name', trans('accounting::accounting.bank.branch'))
                ->optional(),

            Field::string('account_number', trans('accounting::accounting.bank.account_number'))
                ->required(),

            Field::string('iban', trans('accounting::accounting.bank.iban'))
                ->optional(),

            Field::string('swift_code', trans('accounting::accounting.bank.swift'))
                ->optional(),

            Field::select('account_id', trans('accounting::accounting.bank.account_id'))
                ->setOptions([])
                ->required(),

            Field::boolean('active', trans('accounting::accounting.bank.is_active'))
                ->withDefaultValue(true),
        ];
    }

    public function create(Request $request)
    {
        $htmlPageTitle = $this->resolveBankFormHtmlPageTitle(false);

        $this->title($htmlPageTitle);
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $accountId = old('account_id');
        $selectedAccount = $accountId ? Account::query()->find($accountId) : null;

        $this->view
            ->setTpl('banks.form')
            ->withPlugins(['advanced-select'])
            ->withJs('vendor/accounting/admin/js/treasury-auto-account-toggle.js', true)
            ->withVariables([
                'isEdit' => false,
                'bank' => null,
                'selectedAccount' => $selectedAccount,
                'accountSearchUrl' => route('admin.accounting.banks.search-accounts'),
                'htmlPageTitle' => $htmlPageTitle,
                'initialLedgerBranches' => $this->loadInitialAssetLedgerBranchesForBankForm(),
                'autoCreateAccountEnabled' => $this->resolveAutoCreateAccountEnabled($request, true),
            ]);

        return $this->view();
    }

    public function edit(Request $request, int|string $id)
    {
        $bank = Bank::with('account')->findOrFail($id);

        $htmlPageTitle = $this->resolveBankFormHtmlPageTitle(true, $bank);

        $accountId = old('account_id', $bank->account_id);
        $selectedAccount = $accountId ? Account::query()->find($accountId) : null;

        $this->title($htmlPageTitle);
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view
            ->setTpl('banks.form')
            ->withPlugins(['advanced-select'])
            ->withJs('vendor/accounting/admin/js/treasury-auto-account-toggle.js', true)
            ->withVariables([
                'isEdit' => true,
                'bank' => $bank,
                'selectedAccount' => $selectedAccount,
                'accountSearchUrl' => route('admin.accounting.banks.search-accounts'),
                'htmlPageTitle' => $htmlPageTitle,
                'initialLedgerBranches' => $this->loadInitialAssetLedgerBranchesForBankForm(),
                'autoCreateAccountEnabled' => $this->resolveAutoCreateAccountEnabled($request, true),
            ]);

        return $this->view();
    }

    public function show(Request $request, int|string $id)
    {
        $bank = Bank::query()->with('account')->findOrFail((int) $id);
        $statement = app(ReportService::class)->getBankStatementShowData((int) $bank->id, $request->all());

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->title(trans('accounting::accounting.reports.treasury_statement.bank_title', [
            'name' => (string) $bank->name,
        ]));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view
            ->setTheme('admin')
            ->setTpl('banks.show')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/accounting-date-ui.js', true)
            ->withVariables([
                'bank' => $bank,
                'statement' => $statement,
            ]);

        return $this->view();
    }

    /**
     * شاخه‌های اصلی چارت (معمولاً معین سطح ۲) برای نمایش در کشو بدون جستجو — نه هزاران تفصیلی.
     *
     * @return \Illuminate\Support\Collection<int, \RMS\Accounting\Models\Account>
     */
    protected function loadInitialAssetLedgerBranchesForBankForm(): \Illuminate\Support\Collection
    {
        $level = (int) config('accounting.banks.initial_ledger_branch_level', Account::LEVEL_SUBSIDIARY);
        $max = max(1, (int) config('accounting.banks.initial_ledger_branch_max', 40));
        $codes = config('accounting.banks.initial_ledger_branch_codes');

        $query = Account::query()
            ->where('account_type', Account::TYPE_ASSET)
            ->where('active', true)
            ->where('level', $level);

        if (is_array($codes) && count($codes) > 0) {
            $query->whereIn('code', $codes);
        }

        return $query->orderBy('code')->limit($max)->get(['id', 'code', 'name']);
    }

    /**
     * عنوان تب مرورگر و هدر پنل برای فرم بانک (ایجاد / ویرایش).
     */
    protected function resolveBankFormHtmlPageTitle(bool $isEdit, ?Bank $bank = null): string
    {
        $appName = (string) config('app.name', 'RMS');

        if ($isEdit && $bank !== null) {
            return trans('accounting::accounting.bank_form.document_title_edit', [
                'bank' => $bank->name,
                'app' => $appName,
            ]);
        }

        return trans('accounting::accounting.bank_form.document_title_create', [
            'app' => $appName,
        ]);
    }

    /**
     * فقط برای فیلد جستجوی دوم (تفصیلی): حداقل ۲ کاراکتر. شاخهٔ اصلی در سلکت جدا در Blade است.
     */
    public function searchAssetAccounts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $limit = min(50, max(5, (int) $request->query('limit', 30)));

        $accounts = Account::query()
            ->where('account_type', Account::TYPE_ASSET)
            ->where('active', true)
            ->where('level', '>=', 2)
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', '%' . $q . '%')
                    ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->orderBy('code')
            ->limit($limit)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'results' => $this->mapAccountsToSelect2Results($accounts),
        ]);
    }

    /**
     * @param iterable<Account> $accounts
     * @return array<int, array{id: int, text: string}>
     */
    protected function mapAccountsToSelect2Results(iterable $accounts): array
    {
        $out = [];
        foreach ($accounts as $account) {
            $out[] = [
                'id' => $account->id,
                'text' => $account->code . ' — ' . $account->name,
            ];
        }

        return $out;
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.bank.name'))->searchable()->sortable(),
            Field::make('short_name')->withTitle(trans('accounting::accounting.bank.short_name'))->searchable()->width('140px'),
            Field::make('branch_name')->withTitle(trans('accounting::accounting.bank.branch'))->searchable()->width('150px'),
            Field::make('account_number')->withTitle(trans('accounting::accounting.bank.account_number'))->searchable()->width('180px'),
            Field::number('balance')->withTitle(trans('accounting::accounting.bank.balance'))->sortable()->width('150px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.common.status'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'auto_create_account' => ['nullable', 'boolean'],
            'account_id' => [
                'required_if:auto_create_account,0',
                'nullable',
                'integer',
                'exists:accounts,id',
            ],
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

    protected function applyAutoProvisionedAccount(Request &$request): void
    {
        $autoCreateEnabled = $this->resolveAutoCreateAccountEnabled($request, false);
        $requestedAccountId = (int) $request->input('account_id', 0);
        $requestedAccountExists = $requestedAccountId > 0
            && Account::query()->whereKey($requestedAccountId)->exists();

        if (! $autoCreateEnabled) {
            // اگر کاربر حالت دستی را انتخاب کرده، قبل از save باید account_id معتبر باشد.
            if (! $requestedAccountExists) {
                throw ValidationException::withMessages([
                    'account_id' => [trans('validation.exists', ['attribute' => trans('accounting::accounting.bank.account_id')])],
                ]);
            }

            return;
        }

        try {
            $account = app(TreasurySubAccountProvisioningService::class)->provisionFor(
                TreasurySubAccountProvisioningService::TYPE_BANK,
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

        if (is_array($value)) {
            foreach ($value as $item) {
                if (in_array(strtolower(trim((string) $item)), ['1', 'true', 'on', 'yes'], true)) {
                    return true;
                }
            }

            return false;
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

    public function beforeDelete(Request &$request, string|int $id): void
    {
        $bank = Bank::findOrFail($id);

        if (
            CustomerPayment::where('bank_id', $bank->id)->exists()
            || SupplierPayment::where('bank_id', $bank->id)->exists()
            || Expense::where('bank_id', $bank->id)->exists()
        ) {
            throw new \Exception(trans('accounting::accounting.bank_has_transactions'));
        }
    }
}
