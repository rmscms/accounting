<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\ManualJournal;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\TaxRate;
use RMS\Core\Models\Setting;

/**
 * وضعیت پیش‌نیازهای راه‌اندازی حسابداری برای ویزارد onboarding و راهنما.
 */
class AccountingReadinessService
{
    public function __construct(
        protected AccountingInstallService $installService,
        protected FiscalYearService $fiscalYearService,
        protected TreasurySubAccountProvisioningService $treasurySubAccountProvisioningService
    ) {}

    /**
     * @return list<array{
     *   key: string,
     *   tier: 'required'|'recommended'|'optional',
     *   ok: bool,
     *   label: string,
     *   message: string,
     *   action_route: string|null,
     *   action_label: string|null,
     *   action_params?: array<string, scalar|null>|null
     * }>
     */
    public function checklist(): array
    {
        $items = [];

        $installOk = $this->installService->isComplete()
            || ! $this->installService->isWizardRequired();
        $items[] = [
            'key' => 'install_wizard',
            'tier' => 'required',
            'ok' => $installOk,
            'label' => trans('accounting::accounting.readiness.items.install_wizard.label'),
            'message' => $installOk
                ? trans('accounting::accounting.readiness.items.install_wizard.ok')
                : trans('accounting::accounting.readiness.items.install_wizard.fail'),
            'action_route' => 'admin.accounting.install',
            'action_label' => trans('accounting::accounting.readiness.items.install_wizard.action'),
        ];

        $hasAccountsTable = Schema::hasTable('accounts');
        $chartOk = $hasAccountsTable && Account::query()->where('active', true)->count() >= 3;
        $items[] = [
            'key' => 'chart_of_accounts',
            'tier' => 'required',
            'ok' => $chartOk,
            'label' => trans('accounting::accounting.readiness.items.chart_of_accounts.label'),
            'message' => $chartOk
                ? trans('accounting::accounting.readiness.items.chart_of_accounts.ok')
                : trans('accounting::accounting.readiness.items.chart_of_accounts.fail'),
            'action_route' => $chartOk ? null : 'admin.accounting.accounts.index',
            'action_label' => $chartOk ? null : trans('accounting::accounting.readiness.items.chart_of_accounts.action'),
        ];

        $baseCode = Currency::resolveBaseCurrencyCode('IRR');
        $currencyOk = Schema::hasTable('currencies')
            ? Currency::query()->where('code', $baseCode)->where('active', true)->exists()
            : ($baseCode !== '');
        $items[] = [
            'key' => 'base_currency',
            'tier' => 'required',
            'ok' => $currencyOk,
            'label' => trans('accounting::accounting.readiness.items.base_currency.label'),
            'message' => $currencyOk
                ? trans('accounting::accounting.readiness.items.base_currency.ok', ['code' => $baseCode ?: '—'])
                : trans('accounting::accounting.readiness.items.base_currency.fail'),
            'action_route' => 'admin.accounting.currencies.index',
            'action_label' => trans('accounting::accounting.readiness.items.base_currency.action'),
        ];

        $referenceCode = null;
        if (Schema::hasTable('currencies') && Schema::hasColumn('currencies', 'is_reference')) {
            $referenceCode = Currency::query()
                ->where('is_reference', true)
                ->orderByDesc('active')
                ->orderBy('code')
                ->value('code');
        }
        if (!is_string($referenceCode) || trim($referenceCode) === '') {
            $referenceCode = (string) Setting::get('system_fx.reference_currency', '');
        }
        $referenceCode = strtoupper(trim((string) $referenceCode));

        $referenceConfigured = $referenceCode !== '';
        $referenceRateOk = false;
        $referenceMessage = trans('accounting::accounting.readiness.items.reference_currency_and_rate.fail_currency');

        if ($referenceConfigured) {
            if ($referenceCode === $baseCode) {
                $referenceRateOk = true;
                $referenceMessage = trans('accounting::accounting.readiness.items.reference_currency_and_rate.ok_same');
            } elseif (Schema::hasTable('reference_currency_rates')) {
                $referenceRateOk = DB::table('reference_currency_rates')
                    ->where('base_currency_code', $baseCode)
                    ->where('reference_currency_code', $referenceCode)
                    ->where('rate_to_base', '>', 0)
                    ->exists();
                $referenceMessage = $referenceRateOk
                    ? trans('accounting::accounting.readiness.items.reference_currency_and_rate.ok')
                    : trans('accounting::accounting.readiness.items.reference_currency_and_rate.fail_rate');
            } else {
                $referenceRateOk = false;
                $referenceMessage = trans('accounting::accounting.readiness.items.reference_currency_and_rate.fail_rate');
            }
        }

        $items[] = [
            'key' => 'reference_currency_and_rate',
            'tier' => 'required',
            'ok' => $referenceConfigured && $referenceRateOk,
            'label' => trans('accounting::accounting.readiness.items.reference_currency_and_rate.label'),
            'message' => $referenceMessage,
            'action_route' => 'admin.accounting.currencies.reference-rates',
            'action_label' => trans('accounting::accounting.readiness.items.reference_currency_and_rate.action'),
        ];

        $fyOk = false;
        $fyMessage = trans('accounting::accounting.readiness.items.fiscal_year.fail_none');
        if (Schema::hasTable('fiscal_years')) {
            $fy = $this->fiscalYearService->getCurrentFiscalYear();
            if ($fy !== null && $fy->isOpen() && $fy->containsDate(now())) {
                $fyOk = true;
                $fyMessage = trans('accounting::accounting.readiness.items.fiscal_year.ok', [
                    'code' => (string) $fy->year_code,
                ]);
            } elseif ($fy !== null) {
                $fyMessage = trans('accounting::accounting.readiness.items.fiscal_year.fail_state');
            }
        }
        $items[] = [
            'key' => 'fiscal_year',
            'tier' => 'required',
            'ok' => $fyOk,
            'label' => trans('accounting::accounting.readiness.items.fiscal_year.label'),
            'message' => $fyMessage,
            'action_route' => 'admin.accounting.fiscal_years.index',
            'action_label' => trans('accounting::accounting.readiness.items.fiscal_year.action'),
        ];

        $pmOk = ! Schema::hasTable('payment_methods')
            || PaymentMethod::query()->where('active', true)->exists();
        $items[] = [
            'key' => 'payment_methods',
            'tier' => 'recommended',
            'ok' => $pmOk,
            'label' => trans('accounting::accounting.readiness.items.payment_methods.label'),
            'message' => $pmOk
                ? trans('accounting::accounting.readiness.items.payment_methods.ok')
                : trans('accounting::accounting.readiness.items.payment_methods.fail'),
            'action_route' => 'admin.accounting.payment-methods.index',
            'action_label' => trans('accounting::accounting.readiness.items.payment_methods.action'),
        ];

        $taxOk = ! Schema::hasTable('tax_rates')
            || TaxRate::query()->active()->exists();
        $items[] = [
            'key' => 'tax_rates',
            'tier' => 'recommended',
            'ok' => $taxOk,
            'label' => trans('accounting::accounting.readiness.items.tax_rates.label'),
            'message' => $taxOk
                ? trans('accounting::accounting.readiness.items.tax_rates.ok')
                : trans('accounting::accounting.readiness.items.tax_rates.fail'),
            'action_route' => 'admin.accounting.tax-rates.index',
            'action_label' => trans('accounting::accounting.readiness.items.tax_rates.action'),
        ];

        $treasuryConfigOk = $this->hasValidTreasuryParentSettings();
        $treasuryDataOk = false;
        if (Schema::hasTable('banks') || Schema::hasTable('cash_boxes')) {
            $b = Schema::hasTable('banks') ? Bank::query()->where('active', true)->count() : 0;
            $c = Schema::hasTable('cash_boxes') ? CashBox::query()->where('active', true)->count() : 0;
            $treasuryDataOk = $b + $c > 0;
        }
        $treasuryOk = $treasuryConfigOk && $treasuryDataOk;
        $items[] = [
            'key' => 'treasury',
            'tier' => 'recommended',
            'ok' => $treasuryOk,
            'label' => trans('accounting::accounting.readiness.items.treasury.label'),
            'message' => !$treasuryConfigOk
                ? trans('accounting::accounting.readiness.items.treasury.fail_config')
                : ($treasuryOk
                    ? trans('accounting::accounting.readiness.items.treasury.ok')
                    : trans('accounting::accounting.readiness.items.treasury.fail')),
            'action_route' => !$treasuryConfigOk ? 'admin.accounting.settings.index' : 'admin.accounting.banks.index',
            'action_label' => !$treasuryConfigOk
                ? trans('accounting::accounting.readiness.items.treasury.action_settings')
                : trans('accounting::accounting.readiness.items.treasury.action'),
            'action_params' => !$treasuryConfigOk
                ? [
                    'settings_tab' => 'general-tab',
                    'settings_focus_tags' => 'treasury.bank_parent_account_code,treasury.cashbox_parent_account_code',
                ]
                : null,
        ];

        $openingOk = ! Schema::hasTable('manual_journals')
            || ManualJournal::query()->where('status', 'posted')->exists();
        $items[] = [
            'key' => 'opening_journal',
            'tier' => 'optional',
            'ok' => $openingOk,
            'label' => trans('accounting::accounting.readiness.items.opening_journal.label'),
            'message' => $openingOk
                ? trans('accounting::accounting.readiness.items.opening_journal.ok')
                : trans('accounting::accounting.readiness.items.opening_journal.fail'),
            'action_route' => 'admin.accounting.manual-journals.create',
            'action_label' => trans('accounting::accounting.readiness.items.opening_journal.action'),
        ];

        return $items;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   required_total: int,
     *   required_ok: int,
     *   recommended_total: int,
     *   recommended_ok: int,
     *   optional_total: int,
     *   optional_ok: int,
     *   percent: int,
     *   all_required_ok: bool
     * }
     */
    public function summary(): array
    {
        $items = $this->checklist();
        $requiredTotal = 0;
        $requiredOk = 0;
        $recommendedTotal = 0;
        $recommendedOk = 0;
        $optionalTotal = 0;
        $optionalOk = 0;

        foreach ($items as $row) {
            $tier = $row['tier'];
            $ok = (bool) $row['ok'];
            if ($tier === 'required') {
                $requiredTotal++;
                if ($ok) {
                    $requiredOk++;
                }
            } elseif ($tier === 'recommended') {
                $recommendedTotal++;
                if ($ok) {
                    $recommendedOk++;
                }
            } else {
                $optionalTotal++;
                if ($ok) {
                    $optionalOk++;
                }
            }
        }

        $percent = $requiredTotal > 0
            ? (int) round(100 * $requiredOk / $requiredTotal)
            : 0;

        return [
            'items' => $items,
            'required_total' => $requiredTotal,
            'required_ok' => $requiredOk,
            'recommended_total' => $recommendedTotal,
            'recommended_ok' => $recommendedOk,
            'optional_total' => $optionalTotal,
            'optional_ok' => $optionalOk,
            'percent' => $percent,
            'all_required_ok' => $requiredTotal > 0 && $requiredOk === $requiredTotal,
        ];
    }

    protected function hasValidTreasuryParentSettings(): bool
    {
        try {
            $this->treasurySubAccountProvisioningService->resolveParentAccount(TreasurySubAccountProvisioningService::TYPE_BANK);
            $this->treasurySubAccountProvisioningService->resolveParentAccount(TreasurySubAccountProvisioningService::TYPE_CASHBOX);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
