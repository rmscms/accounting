<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Core\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\TaxRate;
use RMS\Accounting\Services\PartyService;
use RMS\Accounting\Services\TreasurySubAccountProvisioningService;

/**
 * کنترلر تنظیمات حسابداری
 */
class SettingsController extends AccountingAdminController
{
    /**
     * @var array<int, string>
     */
    protected array $purchaseFormFieldOptions = [
        'purchase_order_status',
        'purchase_order_notes',
        'supplier_invoice_status',
        'supplier_payment_status',
        'supplier_payment_notes',
    ];

    /**
     * @var array<int, string>
     */
    protected array $salesFormFieldOptions = [
        'customer_invoice_status',
        'customer_invoice_tax_amount',
        'customer_invoice_discount_amount',
    ];

    /**
     * متد abstract از AdminController
     */
    public function table(): string
    {
        return '';
    }

    /**
     * متد abstract از AdminController
     */
    public function modelName(): string
    {
        return '';
    }

    /**
     * نمایش صفحه تنظیمات حسابداری
     */
    public function showSettings(Request $request = null)
    {
        // دریافت تنظیمات فعلی
        $settings = $this->getAllSettings();
        $oldInput = is_array(session()->getOldInput()) ? session()->getOldInput() : [];
        $accounts = $this->resolveSettingsSelectedAccounts($settings, $oldInput);

        $this->title('تنظیمات حسابداری');
        $this->use_package_namespace = true;
        
        $this->view->usePackageNamespace('accounting')
            ->setTpl('settings.index')
            ->withPlugins(['advanced-select'])
            ->withJs('vendor/accounting/admin/js/account-settings-focus.js', true)
            ->withVariables([
                'settings' => $settings,
                'createDefaultSalesCustomerRoute' => route('admin.accounting.settings.create-default-sales-customer'),
                'accountSearchUrl' => route('admin.accounting.settings.search-accounts'),
                'accounts' => $accounts,
            ]);
        
        return $this->view();
    }
    
    /**
     * ذخیره تنظیمات حسابداری
     */
    public function saveSettings(Request $request)
    {
        $this->normalizeNumericInputs($request, [
            'decimal_places',
            'payroll_minimum_wage',
            'vat_rate_reduced',
            'vat_rate_zero',
            'income_tax_rate',
        ]);

        $validated = $request->validate([
            // تنظیمات عمومی
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'accounts_receivable_account_code' => 'nullable|string|max:50',
            'accounts_payable_account_code' => 'nullable|string|max:50',
            'inventory_account_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('accounts', 'code')->where(function ($query) {
                    $query->where('active', true)->where('account_type', Account::TYPE_ASSET);
                }),
            ],
            'cheques_receivable_clearing_account_code' => 'nullable|string|max:50',
            'cheques_payable_clearing_account_code' => 'nullable|string|max:50',
            'fx_gain_account_code' => 'nullable|string|max:50',
            'fx_loss_account_code' => 'nullable|string|max:50',
            'fx_settlement_mode' => 'nullable|string|in:single_account,split_accounts',
            'fx_difference_account_code' => 'nullable|string|max:50',
            'wages_payable_account_code' => 'nullable|string|max:50',
            'social_insurance_payable_account_code' => 'nullable|string|max:50',
            'employee_insurance_payable_account_code' => 'nullable|string|max:50',
            'employer_insurance_payable_account_code' => 'nullable|string|max:50',
            'payroll_tax_payable_account_code' => 'nullable|string|max:50',
            'other_payroll_deductions_payable_account_code' => 'nullable|string|max:50',
            'payroll_seniority_reserve_account_code' => 'nullable|string|max:50',
            'employer_social_insurance_account_code' => 'nullable|string|max:50',
            'payroll_seniority_account_code' => 'nullable|string|max:50',
            'payroll_minimum_wage' => 'nullable|numeric|min:0',
            'payroll_attendance_feature_enabled' => 'nullable|boolean',
            'employee_loans_receivable_account_code' => 'nullable|string|max:50',
            'employee_loan_interest_income_account_code' => 'nullable|string|max:50',
            'bank_interest_income_account_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('accounts', 'code')->where(function ($query) {
                    $query->whereIn('account_type', ['income', 'revenue']);
                }),
            ],
            'bank_charges_account_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('accounts', 'code')->where(function ($query) {
                    $query->where('account_type', 'expense');
                }),
            ],
            'treasury_bank_parent_account_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('accounts', 'code')->where(function ($query) {
                    $query->where('active', true)->where('account_type', Account::TYPE_ASSET);
                }),
            ],
            'treasury_cashbox_parent_account_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('accounts', 'code')->where(function ($query) {
                    $query->where('active', true)->where('account_type', Account::TYPE_ASSET);
                }),
            ],
            'equity_capital_account_code' => 'nullable|string|max:50',
            'shareholder_drawings_account_code' => 'nullable|string|max:50',
            'retained_earnings_account_code' => 'nullable|string|max:50|exists:accounts,code',
            'income_summary_account_code' => 'nullable|string|max:50|exists:accounts,code',
            'sales_default_customer_id' => [
                'nullable',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $id = (int) $value;
                    if ($id > 0 && !Customer::query()->whereKey($id)->exists()) {
                        $fail((string) trans('accounting::accounting.settings.validation.customer_not_found'));
                    }
                },
            ],
            
            // تنظیمات مالیاتی - VAT
            'vat_enabled' => 'nullable|boolean',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_account_payable_id' => 'nullable|integer|exists:accounts,id',
            'vat_account_receivable_id' => 'nullable|integer|exists:accounts,id',
            'vat_rate_reduced' => 'nullable|numeric|min:0|max:100',
            'vat_rate_zero' => 'nullable|numeric|min:0|max:100',
            
            // تنظیمات مالیاتی - Income Tax
            'income_tax_enabled' => 'nullable|boolean',
            'income_tax_rate' => 'nullable|numeric|min:0|max:100',
            'income_tax_expense_account_id' => 'nullable|integer|exists:accounts,id',
            'income_tax_payable_account_id' => 'nullable|integer|exists:accounts,id',
            
            // تنظیمات محاسبات
            'tax_calculation_method' => 'nullable|in:exclusive,inclusive',
            'tax_rounding' => 'nullable|in:round,ceil,floor',

            // تنظیمات فرم‌های خرید/پرداخت (پکیج حسابداری)
            'accounting_purchase_hidden_fields' => 'nullable|array',
            'accounting_purchase_hidden_fields.*' => 'string|in:' . implode(',', $this->purchaseFormFieldOptions),

            'accounting_sales_hidden_fields' => 'nullable|array',
            'accounting_sales_hidden_fields.*' => 'string|in:' . implode(',', $this->salesFormFieldOptions),
        ], [
            'vat_account_payable_id.exists' => (string) trans('accounting::accounting.settings.validation.account_not_found'),
            'vat_account_receivable_id.exists' => (string) trans('accounting::accounting.settings.validation.account_not_found'),
            'income_tax_expense_account_id.exists' => (string) trans('accounting::accounting.settings.validation.account_not_found'),
            'income_tax_payable_account_id.exists' => (string) trans('accounting::accounting.settings.validation.account_not_found'),
            'inventory_account_code.required' => (string) trans('accounting::accounting.settings.validation.inventory_required'),
            'inventory_account_code.exists' => (string) trans('accounting::accounting.settings.validation.inventory_invalid'),
            'bank_interest_income_account_code.exists' => (string) trans('accounting::accounting.settings.validation.account_code_not_found'),
            'bank_charges_account_code.exists' => (string) trans('accounting::accounting.settings.validation.account_code_not_found'),
            'treasury_bank_parent_account_code.required' => (string) trans('accounting::accounting.settings.validation.treasury_parent_required'),
            'treasury_bank_parent_account_code.exists' => (string) trans('accounting::accounting.settings.validation.treasury_parent_invalid'),
            'treasury_cashbox_parent_account_code.required' => (string) trans('accounting::accounting.settings.validation.treasury_parent_required'),
            'treasury_cashbox_parent_account_code.exists' => (string) trans('accounting::accounting.settings.validation.treasury_parent_invalid'),
            'retained_earnings_account_code.exists' => (string) trans('accounting::accounting.settings.validation.account_code_not_found'),
            'income_summary_account_code.exists' => (string) trans('accounting::accounting.settings.validation.account_code_not_found'),
        ], [
            'vat_account_payable_id' => (string) trans('accounting::accounting.settings.fields.vat_account_payable_id'),
            'vat_account_receivable_id' => (string) trans('accounting::accounting.settings.fields.vat_account_receivable_id'),
            'income_tax_expense_account_id' => (string) trans('accounting::accounting.settings.fields.income_tax_expense_account_id'),
            'income_tax_payable_account_id' => (string) trans('accounting::accounting.settings.fields.income_tax_payable_account_id'),
            'inventory_account_code' => (string) trans('accounting::accounting.settings.fields.inventory_account_code'),
            'bank_interest_income_account_code' => (string) trans('accounting::accounting.settings_bank_reconciliation.bank_interest_income'),
            'bank_charges_account_code' => (string) trans('accounting::accounting.settings_bank_reconciliation.bank_charges'),
            'treasury_bank_parent_account_code' => (string) trans('accounting::accounting.settings.fields.treasury_bank_parent_account_code'),
            'treasury_cashbox_parent_account_code' => (string) trans('accounting::accounting.settings.fields.treasury_cashbox_parent_account_code'),
            'sales_default_customer_id' => (string) trans('accounting::accounting.settings.sales_forms.fields.default_customer_id'),
            'retained_earnings_account_code' => (string) trans('accounting::accounting.settings.fields.retained_earnings_account'),
            'income_summary_account_code' => (string) trans('accounting::accounting.settings.fields.income_summary_account'),
        ]);

        $fxMode = (string) ($validated['fx_settlement_mode'] ?? Setting::get('accounting.fx.settlement_mode', 'split_accounts'));
        if ($fxMode !== 'single_account' && $fxMode !== 'split_accounts') {
            $fxMode = 'split_accounts';
        }
        if ($fxMode === 'single_account' && trim((string) $request->input('fx_difference_account_code', '')) === '') {
            throw ValidationException::withMessages([
                'fx_difference_account_code' => (string) trans('accounting::accounting.settings.validation.fx_difference_required_single'),
            ]);
        }
        if ($fxMode === 'split_accounts') {
            if (trim((string) $request->input('fx_gain_account_code', '')) === '') {
                throw ValidationException::withMessages([
                    'fx_gain_account_code' => (string) trans('accounting::accounting.settings.validation.fx_gain_required_split'),
                ]);
            }
            if (trim((string) $request->input('fx_loss_account_code', '')) === '') {
                throw ValidationException::withMessages([
                    'fx_loss_account_code' => (string) trans('accounting::accounting.settings.validation.fx_loss_required_split'),
                ]);
            }
        }

        // ذخیره تنظیمات
        $settingsToSave = [];
        
        // تنظیمات عمومی
        if (isset($validated['decimal_places'])) {
            $settingsToSave['accounting.decimal_places'] = $validated['decimal_places'];
        }
        if (isset($validated['accounts_receivable_account_code'])) {
            $settingsToSave['accounting.system_accounts.assets.accounts_receivable'] = $validated['accounts_receivable_account_code'];
        }
        if (isset($validated['accounts_payable_account_code'])) {
            $settingsToSave['accounting.system_accounts.liabilities.accounts_payable'] = $validated['accounts_payable_account_code'];
        }
        if (isset($validated['inventory_account_code'])) {
            $settingsToSave['accounting.system_accounts.assets.inventory'] = $validated['inventory_account_code'];
        }
        if (isset($validated['cheques_receivable_clearing_account_code'])) {
            $settingsToSave['accounting.system_accounts.assets.cheques_receivable_clearing'] = $validated['cheques_receivable_clearing_account_code'];
        }
        if (isset($validated['cheques_payable_clearing_account_code'])) {
            $settingsToSave['accounting.system_accounts.liabilities.cheques_payable_clearing'] = $validated['cheques_payable_clearing_account_code'];
        }
        $settingsToSave['accounting.fx.settlement_mode'] = $fxMode;
        if ($request->has('fx_gain_account_code')) {
            $settingsToSave['accounting.system_accounts.gains.fx_gain'] = (string) $request->input('fx_gain_account_code', '');
        }
        if ($request->has('fx_loss_account_code')) {
            $settingsToSave['accounting.system_accounts.expenses.fx_loss'] = (string) $request->input('fx_loss_account_code', '');
        }
        if ($request->has('fx_difference_account_code')) {
            $settingsToSave['accounting.system_accounts.fx_difference.account'] = (string) $request->input('fx_difference_account_code', '');
        }
        if ($request->has('sales_default_customer_id')) {
            $settingsToSave['accounting.sales.default_customer_id'] = (string) (int) $request->input('sales_default_customer_id', 0);
        }
        $settingsToSave[TreasurySubAccountProvisioningService::SETTING_BANK_PARENT_ACCOUNT_CODE] = trim((string) $request->input('treasury_bank_parent_account_code', ''));
        $settingsToSave[TreasurySubAccountProvisioningService::SETTING_CASHBOX_PARENT_ACCOUNT_CODE] = trim((string) $request->input('treasury_cashbox_parent_account_code', ''));
        $payrollAccountInputs = [
            'wages_payable_account_code' => 'accounting.system_accounts.liabilities.wages_payable',
            'social_insurance_payable_account_code' => 'accounting.system_accounts.liabilities.social_insurance_payable',
            'employee_insurance_payable_account_code' => 'accounting.system_accounts.liabilities.employee_insurance_payable',
            'employer_insurance_payable_account_code' => 'accounting.system_accounts.liabilities.employer_insurance_payable',
            'payroll_tax_payable_account_code' => 'accounting.system_accounts.liabilities.payroll_tax_payable',
            'other_payroll_deductions_payable_account_code' => 'accounting.system_accounts.liabilities.other_payroll_deductions_payable',
            'payroll_seniority_reserve_account_code' => 'accounting.system_accounts.liabilities.payroll_seniority_reserve',
            'employer_social_insurance_account_code' => 'accounting.system_accounts.expenses.employer_social_insurance',
            'payroll_seniority_account_code' => 'accounting.system_accounts.expenses.payroll_seniority',
            'employee_loans_receivable_account_code' => 'accounting.system_accounts.assets.employee_loans_receivable',
            'employee_loan_interest_income_account_code' => 'accounting.system_accounts.revenue.employee_loan_interest_income',
            'bank_interest_income_account_code' => 'accounting.system_accounts.revenue.bank_interest_income',
            'bank_charges_account_code' => 'accounting.system_accounts.expenses.bank_charges',
            'equity_capital_account_code' => 'accounting.system_accounts.equity.capital',
            'shareholder_drawings_account_code' => 'accounting.system_accounts.equity.shareholder_drawings',
        ];
        foreach ($payrollAccountInputs as $input => $settingKey) {
            if ($request->has($input)) {
                $settingsToSave[$settingKey] = (string) $request->input($input, '');
            }
        }
        if ($request->has('retained_earnings_account_code')) {
            $code = trim((string) $request->input('retained_earnings_account_code', ''));
            $settingsToSave['accounting.retained_earnings_account_id'] = $code === ''
                ? null
                : (int) (Account::query()->where('code', $code)->value('id') ?? 0);
        }
        if ($request->has('income_summary_account_code')) {
            $code = trim((string) $request->input('income_summary_account_code', ''));
            $settingsToSave['accounting.income_summary_account_id'] = $code === ''
                ? null
                : (int) (Account::query()->where('code', $code)->value('id') ?? 0);
        }
        if ($request->has('payroll_minimum_wage')) {
            $settingsToSave['accounting.payroll.minimum_wage'] = (string) (float) $request->input('payroll_minimum_wage', 0);
        }
        $settingsToSave['accounting.payroll.attendance.feature_enabled'] = $request->boolean('payroll_attendance_feature_enabled', true);
        
        // تنظیمات VAT
        $settingsToSave['accounting.vat.enabled'] = $validated['vat_enabled'] ?? false;
        if (isset($validated['vat_account_payable_id'])) {
            $settingsToSave['accounting.vat.account_payable_id'] = $validated['vat_account_payable_id'];
        }
        if (isset($validated['vat_account_receivable_id'])) {
            $settingsToSave['accounting.vat.account_receivable_id'] = $validated['vat_account_receivable_id'];
        }
        if (isset($validated['vat_rate_reduced'])) {
            $settingsToSave['accounting.vat.rate_reduced'] = $validated['vat_rate_reduced'];
        }
        if (isset($validated['vat_rate_zero'])) {
            $settingsToSave['accounting.vat.rate_zero'] = $validated['vat_rate_zero'];
        }
        
        // تنظیمات Income Tax
        $settingsToSave['accounting.income_tax.enabled'] = $validated['income_tax_enabled'] ?? false;
        if (isset($validated['income_tax_rate'])) {
            $settingsToSave['accounting.income_tax.rate'] = $validated['income_tax_rate'];
        }
        if (isset($validated['income_tax_expense_account_id'])) {
            $settingsToSave['accounting.income_tax.expense_account_id'] = $validated['income_tax_expense_account_id'];
        }
        if (isset($validated['income_tax_payable_account_id'])) {
            $settingsToSave['accounting.income_tax.payable_account_id'] = $validated['income_tax_payable_account_id'];
        }
        
        // تنظیمات محاسبات
        if (isset($validated['tax_calculation_method'])) {
            $settingsToSave['accounting.tax.calculation_method'] = $validated['tax_calculation_method'];
            $settingsToSave['accounting.vat.method'] = $validated['tax_calculation_method'];
        }
        if (isset($validated['tax_rounding'])) {
            $settingsToSave['accounting.tax.rounding'] = $validated['tax_rounding'];
        }

        $hiddenFields = array_values(array_unique(array_filter(
            (array) ($validated['accounting_purchase_hidden_fields'] ?? []),
            fn ($field) => in_array($field, $this->purchaseFormFieldOptions, true)
        )));
        $settingsToSave['accounting.package_forms.purchase.hidden_fields'] = json_encode($hiddenFields, JSON_UNESCAPED_UNICODE);

        $salesHiddenFields = array_values(array_unique(array_filter(
            (array) ($validated['accounting_sales_hidden_fields'] ?? []),
            fn ($field) => in_array($field, $this->salesFormFieldOptions, true)
        )));
        $settingsToSave['accounting.package_forms.sales.hidden_fields'] = json_encode($salesHiddenFields, JSON_UNESCAPED_UNICODE);
        
        Setting::setMany($settingsToSave);
        TaxRate::syncVatSettingFromDefault();
        
        return redirect()
            ->route('admin.accounting.settings.index')
            ->with('success', 'تنظیمات حسابداری با موفقیت ذخیره شد.');
    }

    /**
     * @param  array<int,string>  $fields
     */
    private function normalizeNumericInputs(Request $request, array $fields): void
    {
        $normalizedInputs = [];
        foreach ($fields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $rawValue = trim((string) $request->input($field, ''));
            if ($rawValue === '') {
                $normalizedInputs[$field] = '';

                continue;
            }

            $normalizedValue = (string) \RMS\Helper\changeNumberToEn($rawValue);
            $normalizedValue = str_replace([',', '٬', '،', ' '], '', $normalizedValue);
            $normalizedInputs[$field] = $normalizedValue;
        }

        if ($normalizedInputs !== []) {
            $request->merge($normalizedInputs);
        }
    }

    public function createDefaultSalesCustomer(Request $request, PartyService $partyService): RedirectResponse|JsonResponse
    {
        $customer = $partyService->ensureDefaultSalesCustomer();
        $message = (string) trans('accounting::accounting.settings.sales_forms.messages.default_customer_created', [
            'name' => (string) $customer->name,
            'id' => (int) $customer->id,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'customer' => [
                    'id' => (int) $customer->id,
                    'name' => (string) $customer->name,
                ],
            ]);
        }

        return redirect()
            ->route('admin.accounting.settings.index')
            ->with('success', $message);
    }

    public function searchAssetAccounts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $types = collect(explode(',', (string) $request->query('types', '')))
            ->map(static fn ($type) => trim((string) $type))
            ->filter(static fn ($type) => $type !== '')
            ->unique()
            ->values()
            ->all();
        $valueKey = trim((string) $request->query('value_key', 'code')) === 'id' ? 'id' : 'code';
        $limit = min(50, max(5, (int) $request->query('limit', 30)));
        $query = Account::query()
            ->where('active', true)
            ->where(function ($query) use ($q): void {
                $query->where('code', 'like', '%' . $q . '%')
                    ->orWhere('name', 'like', '%' . $q . '%');
            });
        if ($types !== []) {
            $query->whereIn('account_type', $types);
        }

        $accounts = $query->orderBy('code')->limit($limit)->get(['id', 'code', 'name']);

        return response()->json([
            'results' => $accounts->map(static fn (Account $account): array => [
                'id' => $valueKey === 'id'
                    ? (string) (int) $account->id
                    : (string) $account->code,
                'text' => trim((string) $account->code) . ' - ' . trim((string) $account->name),
            ])->values()->all(),
        ]);
    }
    
    /**
     * دریافت همه تنظیمات
     */
    protected function getAllSettings(): array
    {
        $syncedVatRate = TaxRate::syncVatSettingFromDefault();
        $vatRate = is_numeric($syncedVatRate)
            ? (float) $syncedVatRate
            : (float) Setting::get('accounting.vat.rate', 9);
        $taxRatesUrl = Route::has('admin.accounting.tax-rates.index')
            ? route('admin.accounting.tax-rates.index')
            : '';

        $hiddenFieldsRaw = Setting::get('accounting.package_forms.purchase.hidden_fields', '[]');
        $hiddenDecoded = is_string($hiddenFieldsRaw) ? json_decode($hiddenFieldsRaw, true) : $hiddenFieldsRaw;
        $hiddenFields = is_array($hiddenDecoded) ? $hiddenDecoded : [];

        $salesHiddenFieldsRaw = Setting::get('accounting.package_forms.sales.hidden_fields', '[]');
        $salesHiddenDecoded = is_string($salesHiddenFieldsRaw) ? json_decode($salesHiddenFieldsRaw, true) : $salesHiddenFieldsRaw;
        $salesHiddenFields = is_array($salesHiddenDecoded) ? $salesHiddenDecoded : [];
        $generalSalesCustomer = $this->resolveGeneralSalesCustomer();
        $storedSalesDefaultCustomerId = (int) Setting::get('accounting.sales.default_customer_id', 0);
        if ($storedSalesDefaultCustomerId > 0 && !Customer::query()->whereKey($storedSalesDefaultCustomerId)->exists()) {
            $storedSalesDefaultCustomerId = 0;
        }
        $vatPayableId = (int) Setting::get('accounting.vat.account_payable_id', 0);
        if ($vatPayableId <= 0) {
            $vatPayableId = (int) Account::query()->where('code', '2102')->value('id');
        }
        $vatReceivableId = (int) Setting::get('accounting.vat.account_receivable_id', 0);
        if ($vatReceivableId <= 0) {
            $vatReceivableId = (int) Account::query()->where('code', '1105')->value('id');
        }
        $retainedEarningsId = (int) Setting::get('accounting.retained_earnings_account_id', 0);
        if ($retainedEarningsId <= 0) {
            $retainedEarningsId = (int) Account::query()->where('code', '3200')->value('id');
        }
        $incomeSummaryId = (int) Setting::get('accounting.income_summary_account_id', 0);
        if ($incomeSummaryId <= 0) {
            $incomeSummaryId = (int) Account::query()->where('code', '3900')->value('id');
        }
        $inventoryCode = trim((string) Setting::get('accounting.system_accounts.assets.inventory', ''));
        $inventoryLabel = $inventoryCode !== ''
            ? (string) (Account::query()->where('code', $inventoryCode)->value('name') ?? '')
            : '';

        return [
            // تنظیمات عمومی
            'default_currency' => Currency::resolveBaseCurrencyCode('IRR'),
            'decimal_places' => Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0)),
            'accounts_receivable_account_code' => Setting::get('accounting.system_accounts.assets.accounts_receivable', config('accounting.system_accounts.assets.accounts_receivable')),
            'accounts_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.accounts_payable'),
            'inventory_account_code' => $inventoryCode,
            'inventory_account_label' => $inventoryLabel,
            'cheques_receivable_clearing_account_code' => Setting::get('accounting.system_accounts.assets.cheques_receivable_clearing', config('accounting.system_accounts.assets.cheques_receivable_clearing')),
            'cheques_payable_clearing_account_code' => Setting::get('accounting.system_accounts.liabilities.cheques_payable_clearing', config('accounting.system_accounts.liabilities.cheques_payable_clearing')),
            'fx_gain_account_code' => Setting::get('accounting.system_accounts.gains.fx_gain', config('accounting.system_accounts.gains.fx_gain')),
            'fx_loss_account_code' => Setting::get('accounting.system_accounts.expenses.fx_loss', config('accounting.system_accounts.expenses.fx_loss')),
            'fx_settlement_mode' => Setting::get('accounting.fx.settlement_mode', 'split_accounts'),
            'fx_difference_account_code' => Setting::get('accounting.system_accounts.fx_difference.account'),
            'wages_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.wages_payable', config('accounting.system_accounts.liabilities.wages_payable')),
            'social_insurance_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.social_insurance_payable', config('accounting.system_accounts.liabilities.social_insurance_payable')),
            'employee_insurance_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.employee_insurance_payable', config('accounting.system_accounts.liabilities.employee_insurance_payable')),
            'employer_insurance_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.employer_insurance_payable', config('accounting.system_accounts.liabilities.employer_insurance_payable')),
            'payroll_tax_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.payroll_tax_payable', config('accounting.system_accounts.liabilities.payroll_tax_payable')),
            'other_payroll_deductions_payable_account_code' => Setting::get('accounting.system_accounts.liabilities.other_payroll_deductions_payable', config('accounting.system_accounts.liabilities.other_payroll_deductions_payable')),
            'payroll_seniority_reserve_account_code' => Setting::get('accounting.system_accounts.liabilities.payroll_seniority_reserve', config('accounting.system_accounts.liabilities.payroll_seniority_reserve')),
            'employer_social_insurance_account_code' => Setting::get('accounting.system_accounts.expenses.employer_social_insurance', config('accounting.system_accounts.expenses.employer_social_insurance')),
            'payroll_seniority_account_code' => Setting::get('accounting.system_accounts.expenses.payroll_seniority', config('accounting.system_accounts.expenses.payroll_seniority')),
            'payroll_minimum_wage' => Setting::get('accounting.payroll.minimum_wage', 0),
            'payroll_attendance_feature_enabled' => (bool) Setting::get('accounting.payroll.attendance.feature_enabled', true),
            'employee_loans_receivable_account_code' => Setting::get('accounting.system_accounts.assets.employee_loans_receivable', config('accounting.system_accounts.assets.employee_loans_receivable')),
            'employee_loan_interest_income_account_code' => Setting::get('accounting.system_accounts.revenue.employee_loan_interest_income', config('accounting.system_accounts.revenue.employee_loan_interest_income')),
            'bank_interest_income_account_code' => Setting::get('accounting.system_accounts.revenue.bank_interest_income', config('accounting.system_accounts.revenue.bank_interest_income')),
            'bank_charges_account_code' => Setting::get('accounting.system_accounts.expenses.bank_charges', config('accounting.system_accounts.expenses.bank_charges')),
            'treasury_bank_parent_account_code' => (string) Setting::get(TreasurySubAccountProvisioningService::SETTING_BANK_PARENT_ACCOUNT_CODE, ''),
            'treasury_cashbox_parent_account_code' => (string) Setting::get(TreasurySubAccountProvisioningService::SETTING_CASHBOX_PARENT_ACCOUNT_CODE, ''),
            'equity_capital_account_code' => Setting::get('accounting.system_accounts.equity.capital', config('accounting.system_accounts.equity.capital')),
            'shareholder_drawings_account_code' => Setting::get('accounting.system_accounts.equity.shareholder_drawings', config('accounting.system_accounts.equity.shareholder_drawings')),
            'retained_earnings_account_code' => $retainedEarningsId > 0
                ? (string) (Account::query()->whereKey($retainedEarningsId)->value('code') ?? '')
                : '',
            'income_summary_account_code' => $incomeSummaryId > 0
                ? (string) (Account::query()->whereKey($incomeSummaryId)->value('code') ?? '')
                : '',
            
            // مالیات بر ارزش افزوده (VAT)
            'vat_enabled' => Setting::get('accounting.vat.enabled', true),
            'vat_rate' => $vatRate,
            'vat_account_payable_id' => $vatPayableId > 0 ? $vatPayableId : null,
            'vat_account_receivable_id' => $vatReceivableId > 0 ? $vatReceivableId : null,
            'vat_rate_reduced' => Setting::get('accounting.vat.rate_reduced', 0),
            'vat_rate_zero' => Setting::get('accounting.vat.rate_zero', 0),
            'vat_rate_managed_by_tax_rates' => true,
            'vat_rate_tax_rates_url' => $taxRatesUrl,
            
            // مالیات بر درآمد (Income Tax)
            'income_tax_enabled' => Setting::get('accounting.income_tax.enabled', false),
            'income_tax_rate' => Setting::get('accounting.income_tax.rate', 25),
            'income_tax_expense_account_id' => Setting::get('accounting.income_tax.expense_account_id'),
            'income_tax_payable_account_id' => Setting::get('accounting.income_tax.payable_account_id'),
            
            // تنظیمات عمومی محاسبات
            'tax_calculation_method' => Setting::get('accounting.tax.calculation_method', 'exclusive'),
            'tax_rounding' => Setting::get('accounting.tax.rounding', 'round'),
            'purchase_forms' => [
                'hidden_fields' => array_values(array_filter(
                    $hiddenFields,
                    fn ($field) => is_string($field) && in_array($field, $this->purchaseFormFieldOptions, true)
                )),
            ],
            'sales_forms' => [
                'hidden_fields' => array_values(array_filter(
                    $salesHiddenFields,
                    fn ($field) => is_string($field) && in_array($field, $this->salesFormFieldOptions, true)
                )),
            ],
            'sales_default_customer_id' => $storedSalesDefaultCustomerId,
            'sales_default_customer_options' => Customer::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'phone'])
                ->map(static function (Customer $customer): array {
                    $phone = trim((string) ($customer->phone ?? ''));
                    $label = (string) $customer->name;
                    if ($phone !== '') {
                        $label .= ' — '.$phone;
                    }

                    return [
                        'id' => (int) $customer->id,
                        'label' => $label,
                    ];
                })
                ->all(),
            'sales_general_customer_exists' => $generalSalesCustomer !== null,
            'sales_general_customer' => $generalSalesCustomer
                ? [
                    'id' => (int) $generalSalesCustomer->id,
                    'name' => (string) $generalSalesCustomer->name,
                ]
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $oldInput
     */
    protected function resolveSettingsSelectedAccounts(array $settings, array $oldInput = []): \Illuminate\Support\Collection
    {
        $codeFields = [
            'accounts_receivable_account_code',
            'accounts_payable_account_code',
            'inventory_account_code',
            'cheques_receivable_clearing_account_code',
            'cheques_payable_clearing_account_code',
            'fx_gain_account_code',
            'fx_loss_account_code',
            'fx_difference_account_code',
            'wages_payable_account_code',
            'social_insurance_payable_account_code',
            'employee_insurance_payable_account_code',
            'employer_insurance_payable_account_code',
            'payroll_tax_payable_account_code',
            'other_payroll_deductions_payable_account_code',
            'payroll_seniority_reserve_account_code',
            'employer_social_insurance_account_code',
            'payroll_seniority_account_code',
            'employee_loans_receivable_account_code',
            'employee_loan_interest_income_account_code',
            'bank_interest_income_account_code',
            'bank_charges_account_code',
            'treasury_bank_parent_account_code',
            'treasury_cashbox_parent_account_code',
            'equity_capital_account_code',
            'shareholder_drawings_account_code',
            'retained_earnings_account_code',
            'income_summary_account_code',
        ];
        $idFields = [
            'vat_account_payable_id',
            'vat_account_receivable_id',
            'income_tax_expense_account_id',
            'income_tax_payable_account_id',
        ];

        $codes = [];
        foreach ($codeFields as $field) {
            $value = trim((string) ($oldInput[$field] ?? $settings[$field] ?? ''));
            if ($value !== '') {
                $codes[] = $value;
            }
        }

        $ids = [];
        foreach ($idFields as $field) {
            $raw = $oldInput[$field] ?? $settings[$field] ?? null;
            $id = is_numeric($raw) ? (int) $raw : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $codes = array_values(array_unique($codes));
        $ids = array_values(array_unique($ids));
        if ($codes === [] && $ids === []) {
            return collect();
        }

        return Account::query()
            ->where(function ($query) use ($codes, $ids): void {
                if ($codes !== []) {
                    $query->whereIn('code', $codes);
                }
                if ($ids !== []) {
                    if ($codes !== []) {
                        $query->orWhereIn('id', $ids);
                    } else {
                        $query->whereIn('id', $ids);
                    }
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);
    }

    private function resolveGeneralSalesCustomer(): ?Customer
    {
        $configuredId = (int) Setting::get('accounting.sales.default_customer_id', 0);
        if ($configuredId > 0) {
            $configured = Customer::query()->find($configuredId);
            if ($configured) {
                return $configured;
            }
        }

        $fallbackNames = ['مشتری عمومی', 'نقد', 'مشتری نقدی', 'General Customer', 'Cash Customer'];

        return Customer::query()
            ->whereIn('name', $fallbackNames)
            ->orderBy('id')
            ->first();
    }
}
