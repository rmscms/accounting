<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Database\Seeders\AccountsSeeder;
use RMS\Accounting\Database\Seeders\CurrenciesSeeder;
use RMS\Accounting\Database\Seeders\FiscalYearsSeeder;
use RMS\Accounting\Database\Seeders\PaymentMethodsSeeder;
use RMS\Accounting\Database\Seeders\TaxRatesSeeder;
use RMS\Accounting\Models\Account;
use RMS\Core\Models\Setting;
use Throwable;

/**
 * نصب idempotent دادهٔ پایهٔ حسابداری (سید + نگاشت settings برای PartyService).
 */
class AccountingInstallService
{
    public const SETTING_COMPLETED_KEY = 'accounting.install.completed_at';
    public const DEFAULT_BANK_PARENT_ACCOUNT_CODE = '1102';
    public const DEFAULT_CASHBOX_PARENT_ACCOUNT_CODE = '1101';

    /**
     * @var array<string, string> کد حساب در چارت سید شده (AccountsSeeder استاندارد ایران)
     */
    protected array $chartCodeMap = [
        'accounting.system_accounts.assets.accounts_receivable' => '1103',
        'accounting.system_accounts.liabilities.accounts_payable' => '2101',
        'accounting.system_accounts.income.sales_revenue' => '4100',
        'accounting.system_accounts.assets.inventory' => '1104',
        'accounting.system_accounts.equity.capital' => '3100',
        'accounting.system_accounts.equity.retained_earnings' => '3200',
        'accounting.system_accounts.equity.shareholder_drawings' => '3300',
        'accounting.system_accounts.liabilities.wages_payable' => '2103',
        'accounting.system_accounts.liabilities.social_insurance_payable' => '2104',
        'accounting.system_accounts.expenses.employer_social_insurance' => '5210',
    ];

    public function isWizardRequired(): bool
    {
        return (bool) config('accounting.install.require_wizard', true);
    }

    public function isComplete(): bool
    {
        if (!$this->isWizardRequired()) {
            return true;
        }

        $v = Setting::get(self::SETTING_COMPLETED_KEY);
        if ($v === null) {
            return false;
        }

        if (is_bool($v)) {
            return $v;
        }

        $s = strtolower(trim((string) $v));
        if ($s === '' || $s === '0' || $s === 'false' || $s === 'no' || $s === 'off') {
            return false;
        }

        return $this->hasValidTreasuryParentSettings();
    }

    /**
     * @return array{success: bool, steps: list<array{key: string, label: string, type: string, status: string, detail: string}>}
     */
    public function runAll(): array
    {
        $steps = [];

        if (!Schema::hasTable('accounts')) {
            return [
                'success' => false,
                'steps' => [[
                    'key' => 'schema',
                    'label' => trans('accounting::accounting.install.step_schema'),
                    'type' => 'config',
                    'status' => 'error',
                    'detail' => trans('accounting::accounting.install.error_no_accounts_table'),
                ]],
            ];
        }

        $steps[] = $this->runSeederStep('accounts', AccountsSeeder::class, trans('accounting::accounting.install.step_accounts'));
        $steps[] = $this->runSeederStep('currencies', CurrenciesSeeder::class, trans('accounting::accounting.install.step_currencies'));
        $steps[] = $this->runSeederStep('payment_methods', PaymentMethodsSeeder::class, trans('accounting::accounting.install.step_payment_methods'));
        $steps[] = $this->runSeederStep('tax_rates', TaxRatesSeeder::class, trans('accounting::accounting.install.step_tax_rates'));
        $steps[] = $this->runSeederStep('fiscal_years', FiscalYearsSeeder::class, trans('accounting::accounting.install.step_fiscal_years'));

        $steps[] = $this->stepMapChartSettings();
        $steps[] = $this->stepTreasuryParentSettings();

        $steps[] = $this->stepChequeClearing();

        $steps[] = [
            'key' => 'import_customers',
            'label' => trans('accounting::accounting.install.step_import_customers'),
            'type' => 'import',
            'status' => 'skipped',
            'detail' => trans('accounting::accounting.install.import_placeholder'),
        ];

        $steps[] = [
            'key' => 'import_suppliers',
            'label' => trans('accounting::accounting.install.step_import_suppliers'),
            'type' => 'import',
            'status' => 'skipped',
            'detail' => trans('accounting::accounting.install.import_placeholder'),
        ];

        $mapStep = collect($steps)->firstWhere('key', 'map_chart');
        $mapOk = is_array($mapStep) && ($mapStep['status'] ?? '') === 'done';
        $treasuryStep = collect($steps)->firstWhere('key', 'treasury_parent_accounts');
        $treasuryOk = is_array($treasuryStep) && ($treasuryStep['status'] ?? '') === 'done';
        $success = Account::query()->count() > 0 && $mapOk && $treasuryOk;

        if ($success) {
            Setting::set(self::SETTING_COMPLETED_KEY, now()->toIso8601String());
            Setting::clearCache();
        }

        return ['success' => $success, 'steps' => $steps];
    }

    /**
     * @return array{key: string, label: string, type: string, status: string, detail: string}
     */
    protected function runSeederStep(string $key, string $class, string $label): array
    {
        $type = 'seed';
        try {
            Artisan::call('db:seed', ['--class' => $class, '--force' => true]);

            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'status' => 'done',
                'detail' => trim((string) Artisan::output()) ?: trans('accounting::accounting.install.seed_ok'),
            ];
        } catch (\Throwable $e) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{key: string, label: string, type: string, status: string, detail: string}
     */
    protected function stepMapChartSettings(): array
    {
        $key = 'map_chart';
        $label = trans('accounting::accounting.install.step_map_chart');
        $details = [];

        try {
            foreach ($this->chartCodeMap as $settingKey => $code) {
                $account = Account::query()->where('code', $code)->first();
                if (!$account) {
                    $details[] = "{$settingKey}: " . trans('accounting::accounting.install.missing_code', ['code' => $code]);

                    continue;
                }
                Setting::set($settingKey, (string) $account->code);
                $details[] = "{$settingKey}={$account->code}";
            }

            $ar = Account::query()->where('code', $this->chartCodeMap['accounting.system_accounts.assets.accounts_receivable'] ?? '')->first();
            if ($ar) {
                Setting::set('accounting.ar_account_id', (string) $ar->id);
            }

            Setting::clearCache();

            $ok = Account::query()->where('code', $this->chartCodeMap['accounting.system_accounts.assets.accounts_receivable'])->exists()
                && Setting::get('accounting.system_accounts.assets.accounts_receivable');

            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => $ok ? 'done' : 'error',
                'detail' => implode(' | ', $details) ?: trans('accounting::accounting.install.map_ok'),
            ];
        } catch (\Throwable $e) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{key: string, label: string, type: string, status: string, detail: string}
     */
    protected function stepChequeClearing(): array
    {
        $key = 'cheque_clearing';
        $label = trans('accounting::accounting.install.step_cheque_clearing');

        if (!config('accounting.allow_dashboard_cheque_clearing_setup', true)) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'skipped',
                'detail' => trans('accounting::accounting.install.cheque_disabled'),
            ];
        }

        try {
            /** @var ChequeClearingAccountSetupService $svc */
            $svc = app(ChequeClearingAccountSetupService::class);
            $r = $svc->run(writeEnv: false);

            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'done',
                'detail' => trans('accounting::accounting.install.cheque_ok', [
                    'ar' => $r['receivable_account_id'],
                    'ap' => $r['payable_account_id'],
                ]),
            ];
        } catch (\Throwable $e) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{key: string, label: string, type: string, status: string, detail: string}
     */
    protected function stepTreasuryParentSettings(): array
    {
        $key = 'treasury_parent_accounts';
        $label = trans('accounting::accounting.install.step_treasury_parent_accounts');

        try {
            $bankCode = self::DEFAULT_BANK_PARENT_ACCOUNT_CODE;
            $cashboxCode = self::DEFAULT_CASHBOX_PARENT_ACCOUNT_CODE;

            $bankParent = Account::query()
                ->where('code', $bankCode)
                ->where('active', true)
                ->where('account_type', Account::TYPE_ASSET)
                ->first();
            if (!$bankParent instanceof Account) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'type' => 'config',
                    'status' => 'error',
                    'detail' => (string) trans('accounting::accounting.install.missing_treasury_parent_account', [
                        'type' => trans('accounting::accounting.treasury_sub_accounts.types.bank'),
                        'code' => $bankCode,
                    ]),
                ];
            }

            $cashboxParent = Account::query()
                ->where('code', $cashboxCode)
                ->where('active', true)
                ->where('account_type', Account::TYPE_ASSET)
                ->first();
            if (!$cashboxParent instanceof Account) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'type' => 'config',
                    'status' => 'error',
                    'detail' => (string) trans('accounting::accounting.install.missing_treasury_parent_account', [
                        'type' => trans('accounting::accounting.treasury_sub_accounts.types.cashbox'),
                        'code' => $cashboxCode,
                    ]),
                ];
            }

            Setting::set(TreasurySubAccountProvisioningService::SETTING_BANK_PARENT_ACCOUNT_CODE, $bankCode);
            Setting::set(TreasurySubAccountProvisioningService::SETTING_CASHBOX_PARENT_ACCOUNT_CODE, $cashboxCode);
            Setting::clearCache();

            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'done',
                'detail' => (string) trans('accounting::accounting.install.treasury_parent_accounts_ok', [
                    'bank' => $bankCode,
                    'cashbox' => $cashboxCode,
                ]),
            ];
        } catch (Throwable $e) {
            return [
                'key' => $key,
                'label' => $label,
                'type' => 'config',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    protected function hasValidTreasuryParentSettings(): bool
    {
        try {
            $svc = app(TreasurySubAccountProvisioningService::class);
            $svc->resolveParentAccount(TreasurySubAccountProvisioningService::TYPE_BANK);
            $svc->resolveParentAccount(TreasurySubAccountProvisioningService::TYPE_CASHBOX);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
