<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\ExpenseCategory;
use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use RMS\Accounting\Services\ScenarioRunnerService;
use RMS\Accounting\Services\ScenarioRunnerStateStore;
use RMS\Core\Models\Setting;
use Throwable;

class AccountingScenarioRunnerController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function index(Request $request)
    {
        /** @var ScenarioRunnerService $service */
        $service = app(ScenarioRunnerService::class);
        $scenarioDefinitions = $service->scenarioDefinitions();
        /** @var ScenarioRunnerStateStore $stateStore */
        $stateStore = app(ScenarioRunnerStateStore::class);
        $scenarioStateData = $stateStore->getStateWithDefinitions($scenarioDefinitions);
        $formValues = $request->session()->get('accounting_scenario_runner.form_values');
        if (! is_array($formValues)) {
            $formValues = $service->defaultFormValues();
        }

        $preview = $request->session()->get('accounting_scenario_runner.preview');
        if (! is_array($preview)) {
            $preview = null;
        }

        $result = $request->session()->get('accounting_scenario_runner.result');
        if (! is_array($result)) {
            $result = null;
        }
        $focusTarget = (string) $request->session()->pull('accounting_scenario_runner.focus_target', '');

        $entityOptions = [
            'customers' => Customer::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (Customer $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'suppliers' => Supplier::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (Supplier $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'banks' => Bank::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (Bank $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'cash_boxes' => CashBox::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (CashBox $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'wallets' => Wallet::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'wallet_type'])
                ->map(static fn (Wallet $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'wallet_type' => (string) $row->wallet_type])
                ->values()
                ->all(),
            'chequebooks' => Chequebook::query()
                ->where('active', true)
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'title'])
                ->map(static fn (Chequebook $row): array => ['id' => (int) $row->id, 'title' => (string) $row->title])
                ->values()
                ->all(),
            'cash_payment_methods' => PaymentMethod::query()
                ->where('active', true)
                ->where('type', PaymentMethod::TYPE_CASH)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (PaymentMethod $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'cheque_payment_methods' => PaymentMethod::query()
                ->where('active', true)
                ->where('type', PaymentMethod::TYPE_CHEQUE)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (PaymentMethod $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'expense_categories' => ExpenseCategory::query()
                ->active()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (ExpenseCategory $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'fixed_asset_categories' => FixedAssetCategory::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (FixedAssetCategory $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'shareholders' => Shareholder::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(static fn (Shareholder $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
        ];
        $currencyOptions = Currency::query()
            ->where('active', true)
            ->orderBy('code')
            ->pluck('code', 'code')
            ->mapWithKeys(static fn ($code, $key): array => [(string) $key => (string) $code])
            ->all();
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $currencyDecimals = Currency::query()
            ->where('code', $baseCurrencyCode)
            ->value('decimals');
        $settingsDecimalPlaces = (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0));
        $amountDecimalPlaces = is_numeric($currencyDecimals)
            ? (int) $currencyDecimals
            : $settingsDecimalPlaces;
        $amountDecimalPlaces = max(0, min(6, $amountDecimalPlaces));

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('scenario_runner.index')
            ->withPlugins(['persian-datepicker'])
            ->withCss('vendor/accounting/admin/css/scenario-runner.css', true)
            ->withJs('vendor/accounting/admin/js/sales-customer-picker.js', true)
            ->withJs('vendor/accounting/admin/js/entity-card-picker.js', true)
            ->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true)
            ->withJs('vendor/accounting/admin/js/scenario-runner.js', true)
            ->withVariables([
                'scenarios' => $scenarioDefinitions,
                'formValues' => $formValues,
                'preview' => $preview,
                'result' => $result,
                'focusTarget' => $focusTarget,
                'previewRoute' => route('admin.accounting.scenario-runner.preview'),
                'runRoute' => route('admin.accounting.scenario-runner.run'),
                'resetRoute' => route('admin.accounting.scenario-runner.reset'),
                'entityOptions' => $entityOptions,
                'customerSearchUrl' => route('admin.accounting.customer-invoices.search-customers'),
                'supplierSearchUrl' => route('admin.accounting.supplier-invoices.search-suppliers'),
                'currencyOptions' => $currencyOptions,
                'baseCurrencyCode' => $baseCurrencyCode,
                'amountDecimalPlaces' => $amountDecimalPlaces,
                'scenarioStateRows' => (array) data_get($scenarioStateData, 'rows', []),
                'scenarioStateSummary' => (array) data_get($scenarioStateData, 'summary', []),
                'scenarioStateFilePath' => (string) data_get($scenarioStateData, 'file_path', ''),
                'scenarioErrorLogsRouteTemplate' => route('admin.accounting.scenario-runner.errors', ['scenarioKey' => '__SCENARIO_KEY__']),
            ]);

        return $this->view();
    }

    public function preview(Request $request, ScenarioRunnerService $service): RedirectResponse
    {
        $validated = $this->validateScenarioPayload($request, $service);

        try {
            $preview = $service->preview($validated);
            $request->session()->put('accounting_scenario_runner.preview', $preview);
            $request->session()->put('accounting_scenario_runner.form_values', $validated);
            $request->session()->forget('accounting_scenario_runner.result');
            $request->session()->put('accounting_scenario_runner.focus_target', 'preview');

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with('success', (string) trans('accounting::accounting.scenario_runner.messages.preview_ready'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with('error', $e->getMessage());
        }
    }

    public function run(Request $request, ScenarioRunnerService $service): RedirectResponse
    {
        $validated = $this->validateScenarioPayload($request, $service);
        /** @var ScenarioRunnerStateStore $stateStore */
        $stateStore = app(ScenarioRunnerStateStore::class);
        $scenarioDefinitions = $service->scenarioDefinitions();
        $scenarioKey = (string) data_get($validated, 'scenario_key', '');

        try {
            $result = $service->execute($validated);
            $request->session()->put('accounting_scenario_runner.result', $result);
            $request->session()->put('accounting_scenario_runner.form_values', $validated);
            $request->session()->put('accounting_scenario_runner.preview', $service->preview($validated));
            $request->session()->put('accounting_scenario_runner.focus_target', 'result');

            $ok = (bool) data_get($result, 'ok', data_get($result, 'diff.ok', false));
            if ($scenarioKey !== '') {
                $statusMessage = $ok
                    ? (string) trans('accounting::accounting.scenario_runner.messages.run_success')
                    : (string) trans('accounting::accounting.scenario_runner.messages.run_with_diff');
                $stateStore->recordRun($scenarioDefinitions, $scenarioKey, $ok, $statusMessage, ! $ok);
            }

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with($ok ? 'success' : 'error', $ok
                    ? (string) trans('accounting::accounting.scenario_runner.messages.run_success')
                    : (string) trans('accounting::accounting.scenario_runner.messages.run_with_diff'));
        } catch (Throwable $e) {
            report($e);
            if ($scenarioKey !== '') {
                try {
                    $stateStore->recordRun($scenarioDefinitions, $scenarioKey, false, $e->getMessage(), true);
                } catch (Throwable $stateError) {
                    report($stateError);
                }
            }

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with('error', $e->getMessage());
        }
    }

    public function reset(Request $request, ScenarioRunnerService $service): RedirectResponse
    {
        /** @var ScenarioRunnerStateStore $stateStore */
        $stateStore = app(ScenarioRunnerStateStore::class);

        try {
            $stateStore->resetAll($service->scenarioDefinitions());
            $request->session()->forget('accounting_scenario_runner.result');

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with('success', (string) trans('accounting::accounting.scenario_runner.messages.state_reset'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.accounting.scenario-runner.index')
                ->with('error', $e->getMessage());
        }
    }

    public function errors(Request $request, ScenarioRunnerService $service, string $scenarioKey): JsonResponse
    {
        /** @var ScenarioRunnerStateStore $stateStore */
        $stateStore = app(ScenarioRunnerStateStore::class);
        $scenarioDefinitions = $service->scenarioDefinitions();
        if (! isset($scenarioDefinitions[$scenarioKey])) {
            return response()->json([
                'ok' => false,
                'message' => 'Scenario not found.',
            ], 404);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(200, $limit));
        $logs = $stateStore->getScenarioErrorLogs($scenarioDefinitions, $scenarioKey, $limit);

        return response()->json([
            'ok' => true,
            'scenario_key' => $scenarioKey,
            'scenario_title' => (string) data_get($scenarioDefinitions, $scenarioKey.'.title', $scenarioKey),
            'count' => count($logs),
            'errors' => $logs,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validateScenarioPayload(Request $request, ScenarioRunnerService $service): array
    {
        $definitions = $service->scenarioDefinitions();
        $scenarioKey = (string) $request->input('scenario_key', '');
        $requiredFields = (array) ($definitions[$scenarioKey]['required_fields'] ?? []);
        $toNullablePositiveInt = static function ($value): ?int {
            if ($value === null || $value === '') {
                return null;
            }
            $normalized = trim((string) \RMS\Helper\changeNumberToEn((string) $value));
            if ($normalized === '' || $normalized === '0') {
                return null;
            }
            if (! is_numeric($normalized)) {
                return null;
            }
            $intVal = (int) $normalized;

            return $intVal > 0 ? $intVal : null;
        };

        $amountRaw = trim((string) \RMS\Helper\changeNumberToEn((string) $request->input('amount', '')));
        $amountNormalized = str_replace([',', '٬', '،', ' '], '', $amountRaw);
        $scenarioDateInput = trim((string) $request->input('scenario_date', ''));
        $scenarioDateNormalized = $this->dateInputNormalizer()->normalizeFilterDateToGregorian($scenarioDateInput)
            ?? trim((string) \RMS\Helper\changeNumberToEn($scenarioDateInput));
        $valueDateInput = trim((string) $request->input('value_date', ''));
        $valueDateNormalized = $valueDateInput === ''
            ? null
            : ($this->dateInputNormalizer()->normalizeFilterDateToGregorian($valueDateInput)
                ?? trim((string) \RMS\Helper\changeNumberToEn($valueDateInput)));

        $request->merge([
            'amount' => $amountNormalized,
            'scenario_date' => $scenarioDateNormalized,
            'value_date' => $valueDateNormalized,
            'customer_id' => $toNullablePositiveInt($request->input('customer_id')),
            'supplier_id' => $toNullablePositiveInt($request->input('supplier_id')),
            'bank_id' => $toNullablePositiveInt($request->input('bank_id')),
            'cash_box_id' => $toNullablePositiveInt($request->input('cash_box_id')),
            'wallet_id' => $toNullablePositiveInt($request->input('wallet_id')),
            'chequebook_id' => $toNullablePositiveInt($request->input('chequebook_id')),
            'payment_method_id' => $toNullablePositiveInt($request->input('payment_method_id')),
            'expense_category_id' => $toNullablePositiveInt($request->input('expense_category_id')),
            'fixed_asset_category_id' => $toNullablePositiveInt($request->input('fixed_asset_category_id')),
            'shareholder_id' => $toNullablePositiveInt($request->input('shareholder_id')),
            'from_treasury_id' => $toNullablePositiveInt($request->input('from_treasury_id')),
            'to_treasury_id' => $toNullablePositiveInt($request->input('to_treasury_id')),
        ]);

        $amountMin = $this->resolveScenarioAmountMinimum($request, $scenarioKey);
        $amountMinRule = 'min:'.$this->normalizeMinRuleNumber($amountMin);
        $allowedFromTreasuryTypes = ['wallet', 'cashbox', 'bank'];
        $allowedToTreasuryTypes = ['wallet', 'cashbox', 'bank'];
        if ($scenarioKey === ScenarioRunnerService::SCENARIO_BANK_TRANSFER) {
            $allowedFromTreasuryTypes = ['wallet'];
            $allowedToTreasuryTypes = ['bank'];
        } elseif ($scenarioKey === ScenarioRunnerService::SCENARIO_BANK_TRANSFER_CASHBOX) {
            $allowedFromTreasuryTypes = ['cashbox'];
            $allowedToTreasuryTypes = ['bank'];
        }
        $validateTreasuryEndpointExists = static function (string $typeField) use ($request) {
            return static function (string $attribute, $value, $fail) use ($request, $typeField): void {
                if ($value === null || $value === '') {
                    return;
                }
                $endpointId = (int) $value;
                if ($endpointId <= 0) {
                    return;
                }

                $type = strtolower(trim((string) $request->input($typeField, '')));
                $exists = match ($type) {
                    'wallet' => Wallet::query()->where('active', true)->whereKey($endpointId)->exists(),
                    'cashbox' => CashBox::query()->where('active', true)->whereKey($endpointId)->exists(),
                    'bank' => Bank::query()->where('active', true)->whereKey($endpointId)->exists(),
                    default => false,
                };
                if (! $exists) {
                    $fail('خزانه انتخاب‌شده برای '.$attribute.' معتبر نیست.');
                }
            };
        };
        $validateDifferentTreasuryEndpoints = static function (string $attribute, $value, $fail) use ($request): void {
            $fromType = strtolower(trim((string) $request->input('from_treasury_type', '')));
            $toType = strtolower(trim((string) $request->input('to_treasury_type', '')));
            $fromId = (int) ($request->input('from_treasury_id') ?? 0);
            $toId = (int) ($request->input('to_treasury_id') ?? 0);
            if ($fromType !== '' && $toType !== '' && $fromId > 0 && $toId > 0 && $fromType === $toType && $fromId === $toId) {
                $fail('خزانه مبدا و مقصد انتقال نمی‌توانند یکسان باشند.');
            }
        };

        $validated = $request->validate([
            'scenario_key' => ['required', 'string', Rule::in(array_keys($definitions))],
            'amount' => ['required', 'numeric', $amountMinRule],
            'scenario_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'customer_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('customer_id', $requiredFields, true))],
            'supplier_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('supplier_id', $requiredFields, true))],
            'bank_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('bank_id', $requiredFields, true))],
            'cash_box_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('cash_box_id', $requiredFields, true))],
            'wallet_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('wallet_id', $requiredFields, true))],
            'chequebook_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('chequebook_id', $requiredFields, true))],
            'payment_method_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('payment_method_id', $requiredFields, true))],
            'expense_category_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('expense_category_id', $requiredFields, true))],
            'fixed_asset_category_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('fixed_asset_category_id', $requiredFields, true))],
            'shareholder_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('shareholder_id', $requiredFields, true))],

            'from_treasury_type' => ['nullable', 'string', Rule::in($allowedFromTreasuryTypes), Rule::requiredIf(in_array('from_treasury_type', $requiredFields, true))],
            'from_treasury_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('from_treasury_id', $requiredFields, true)), $validateTreasuryEndpointExists('from_treasury_type')],
            'to_treasury_type' => ['nullable', 'string', Rule::in($allowedToTreasuryTypes), Rule::requiredIf(in_array('to_treasury_type', $requiredFields, true))],
            'to_treasury_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array('to_treasury_id', $requiredFields, true)), $validateTreasuryEndpointExists('to_treasury_type'), $validateDifferentTreasuryEndpoints],
            'value_date' => ['nullable', 'date', Rule::requiredIf(in_array('value_date', $requiredFields, true))],
            'transfer_fee' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(in_array('transfer_fee', $requiredFields, true))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => 'فیلد :attribute الزامی است.',
            'numeric' => 'فیلد :attribute باید عددی باشد.',
            'integer' => 'فیلد :attribute باید عدد صحیح باشد.',
            'min.numeric' => 'مقدار :attribute نباید کمتر از :min باشد.',
            'date' => 'فرمت :attribute معتبر نیست.',
            'in' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.',
            'max.string' => 'طول :attribute نباید بیشتر از :max کاراکتر باشد.',
        ], [
            'scenario_key' => (string) trans('accounting::accounting.scenario_runner.form.scenario'),
            'amount' => (string) trans('accounting::accounting.scenario_runner.form.amount'),
            'scenario_date' => (string) trans('accounting::accounting.scenario_runner.form.scenario_date'),
            'notes' => (string) trans('accounting::accounting.scenario_runner.form.notes'),
            'customer_id' => (string) trans('accounting::accounting.scenario_runner.form.customer'),
            'supplier_id' => (string) trans('accounting::accounting.scenario_runner.form.supplier'),
            'bank_id' => (string) trans('accounting::accounting.scenario_runner.form.bank'),
            'cash_box_id' => (string) trans('accounting::accounting.scenario_runner.form.cash_box'),
            'wallet_id' => (string) trans('accounting::accounting.scenario_runner.form.wallet'),
            'chequebook_id' => (string) trans('accounting::accounting.scenario_runner.form.chequebook'),
            'payment_method_id' => (string) trans('accounting::accounting.scenario_runner.form.cash_payment_method'),
            'expense_category_id' => (string) trans('accounting::accounting.scenario_runner.form.expense_category'),
            'fixed_asset_category_id' => (string) trans('accounting::accounting.scenario_runner.form.fixed_asset_category'),
            'shareholder_id' => (string) trans('accounting::accounting.scenario_runner.form.shareholder'),
            'from_treasury_type' => (string) trans('accounting::accounting.scenario_runner.form.from_treasury_type'),
            'from_treasury_id' => (string) trans('accounting::accounting.scenario_runner.form.from_treasury_id'),
            'to_treasury_type' => (string) trans('accounting::accounting.scenario_runner.form.to_treasury_type'),
            'to_treasury_id' => (string) trans('accounting::accounting.scenario_runner.form.to_treasury_id'),
            'value_date' => (string) trans('accounting::accounting.scenario_runner.form.value_date'),
            'transfer_fee' => (string) trans('accounting::accounting.scenario_runner.form.transfer_fee'),
            'reference_number' => (string) trans('accounting::accounting.scenario_runner.form.reference_number'),
            'description' => (string) trans('accounting::accounting.scenario_runner.form.description'),
        ]);

        if ($scenarioKey === ScenarioRunnerService::SCENARIO_CUSTOMER_ADVANCE_APPLY) {
            $bankId = (int) ($validated['bank_id'] ?? 0);
            $cashBoxId = (int) ($validated['cash_box_id'] ?? 0);
            $walletId = (int) ($validated['wallet_id'] ?? 0);
            $paymentMethodId = (int) ($validated['payment_method_id'] ?? 0);

            if ($bankId <= 0 && $cashBoxId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bank_id' => 'برای سناریوی اعمال پیش‌دریافت مشتری باید مقصد دریافت (بانک یا صندوق) انتخاب شود.',
                ]);
            }
            if ($walletId > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'wallet_id' => 'در سناریوی اعمال پیش‌دریافت مشتری، مقصد کیف‌پول پشتیبانی نمی‌شود. لطفاً بانک یا صندوق را انتخاب کنید.',
                ]);
            }
            $selectionValidation = app(PaymentDestinationCatalog::class)->validateSelection(
                PaymentDestinationCatalog::CONTEXT_CUSTOMER_PAYMENT,
                $paymentMethodId,
                $bankId > 0 ? $bankId : null,
                $cashBoxId > 0 ? $cashBoxId : null,
                null,
                null,
                null
            );
            if (! (bool) ($selectionValidation['ok'] ?? false)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_method_id' => (string) ($selectionValidation['message'] ?? 'ترکیب روش پرداخت و مقصد انتخاب‌شده معتبر نیست.'),
                ]);
            }
        }

        return $validated;
    }

    private function dateInputNormalizer(): AccountingDateInputNormalizer
    {
        /** @var AccountingDateInputNormalizer $normalizer */
        $normalizer = app(AccountingDateInputNormalizer::class);

        return $normalizer;
    }

    private function resolveScenarioAmountMinimum(Request $request, string $scenarioKey): float
    {
        $currencyCode = $this->resolveScenarioCurrencyCodeForValidation($request, $scenarioKey);
        $decimals = Currency::query()
            ->where('code', $currencyCode)
            ->value('decimals');

        if (! is_numeric($decimals)) {
            $decimals = Setting::get('accounting.decimal_places', config('accounting.decimal_places', 2));
        }

        $precision = max(0, min(6, (int) $decimals));
        $minimum = 1 / (10 ** $precision);

        return round($minimum, $precision === 0 ? 0 : $precision);
    }

    private function resolveScenarioCurrencyCodeForValidation(Request $request, string $scenarioKey): string
    {
        $baseCurrency = Currency::resolveBaseCurrencyCode('IRR');
        $walletId = (int) ($request->input('wallet_id') ?? 0);
        $cashBoxId = (int) ($request->input('cash_box_id') ?? 0);
        $bankId = (int) ($request->input('bank_id') ?? 0);
        $fromTreasuryType = strtolower(trim((string) $request->input('from_treasury_type', '')));
        $fromTreasuryId = (int) ($request->input('from_treasury_id') ?? 0);

        $resolveWalletCurrency = static function (int $id): string {
            if ($id <= 0) {
                return '';
            }

            return strtoupper((string) Wallet::query()->whereKey($id)->value('currency_code'));
        };
        $resolveCashBoxCurrency = static function (int $id): string {
            if ($id <= 0) {
                return '';
            }

            return strtoupper((string) CashBox::query()->whereKey($id)->value('currency_code'));
        };
        $resolveBankCurrency = static function (int $id): string {
            if ($id <= 0) {
                return '';
            }

            return strtoupper((string) Bank::query()->whereKey($id)->value('currency_code'));
        };

        if ($scenarioKey === ScenarioRunnerService::SCENARIO_BANK_TRANSFER
            || $scenarioKey === ScenarioRunnerService::SCENARIO_BANK_TRANSFER_CASHBOX) {
            return match ($fromTreasuryType) {
                'wallet' => $resolveWalletCurrency($fromTreasuryId) ?: $baseCurrency,
                'cashbox' => $resolveCashBoxCurrency($fromTreasuryId) ?: $baseCurrency,
                'bank' => $resolveBankCurrency($fromTreasuryId) ?: $baseCurrency,
                default => $baseCurrency,
            };
        }

        if ($walletId > 0) {
            return $resolveWalletCurrency($walletId) ?: $baseCurrency;
        }
        if ($cashBoxId > 0) {
            return $resolveCashBoxCurrency($cashBoxId) ?: $baseCurrency;
        }
        if ($bankId > 0) {
            return $resolveBankCurrency($bankId) ?: $baseCurrency;
        }

        return $baseCurrency;
    }

    private function normalizeMinRuleNumber(float $value): string
    {
        $normalized = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $normalized !== '' ? $normalized : '0';
    }
}

