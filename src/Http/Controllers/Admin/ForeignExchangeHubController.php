<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use App\Services\Treasury\TreasuryFxConversionService;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ParsesAccountingMoneyInput;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Wallet;

class ForeignExchangeHubController extends AccountingAdminController
{
    use ParsesAccountingMoneyInput;

    public function index(Request $request)
    {
        $this->title('مرکز تبدیل ارز');

        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('foreign_exchange.index')
            ->withPlugins(['amount-formatter', 'advanced-select'])
            ->withCss('vendor/accounting/admin/css/fx-hub.css', true)
            ->withJs('vendor/accounting/admin/js/fx-hub.js', true)
            ->withVariables([
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
                'cashBoxes' => CashBox::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
                'wallets' => Wallet::query()
                    ->where('active', true)
                    ->where('wallet_type', Wallet::TYPE_TREASURY)
                    ->orderBy('id')
                    ->get(['id', 'wallet_type', 'currency_code']),
                'currencies' => Currency::query()->where('active', true)->orderBy('code')->get(['code', 'name']),
                'walletCreateUrl' => Route::has('admin.accounting.wallets.create') ? route('admin.accounting.wallets.create') : null,
                'walletReportUrl' => Route::has('admin.accounting.reports.wallet-report') ? route('admin.accounting.reports.wallet-report') : null,
                'currenciesManageUrl' => Route::has('admin.accounting.currencies.index') ? route('admin.accounting.currencies.index') : null,
                'baseCurrencyCode' => Currency::resolveBaseCurrencyCode('IRR'),
                'amountDecimalPlaces' => (int) $this->resolveAccountingAmountDecimalPlaces(),
                'fxConversionStoreUrl' => route('admin.accounting.foreign-exchange.store'),
            ]);

        return $this->view();
    }

    public function postConversion(Request $request, TreasuryFxConversionService $service)
    {
        $data = $request->validate([
            'store_id' => ['nullable', 'integer'],
            'source_channel_type' => ['required', 'in:bank,cash_box'],
            'source_channel_id' => ['required', 'integer', 'min:1'],
            'target_wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'target_currency_code' => ['required', 'string', 'size:3'],
            'target_amount' => ['required', 'numeric', 'min:0.0001'],
            'source_amount_base' => ['required', 'numeric', 'min:0.0001'],
            'fx_rate_to_base' => ['required', 'numeric', 'min:0.000001'],
            'fee_type' => ['nullable', 'in:fixed,percent'],
            'fee_value' => ['nullable', 'numeric', 'min:0'],
            'fee_amount_base' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sourceChannelType = (string) $data['source_channel_type'];
        $sourceChannelId = (int) $data['source_channel_id'];
        if ($sourceChannelType === 'bank' && ! Bank::query()->whereKey($sourceChannelId)->exists()) {
            throw ValidationException::withMessages([
                'source_channel_id' => [trans('validation.exists', ['attribute' => 'source_channel_id'])],
            ]);
        }
        if ($sourceChannelType === 'cash_box' && ! CashBox::query()->whereKey($sourceChannelId)->exists()) {
            throw ValidationException::withMessages([
                'source_channel_id' => [trans('validation.exists', ['attribute' => 'source_channel_id'])],
            ]);
        }

        $targetCurrencyCode = strtoupper((string) $data['target_currency_code']);
        $wallet = Wallet::query()->findOrFail((int) $data['target_wallet_id']);
        if ((string) ($wallet->wallet_type ?? '') !== Wallet::TYPE_TREASURY) {
            throw ValidationException::withMessages([
                'target_wallet_id' => [trans('accounting::accounting.fx_hub.validation.wallet_treasury_required')],
            ]);
        }
        $walletCurrencyCode = strtoupper((string) ($wallet->currency_code ?? ''));
        if ($walletCurrencyCode !== $targetCurrencyCode) {
            throw ValidationException::withMessages([
                'target_wallet_id' => [trans('accounting::accounting.fx_hub.validation.wallet_currency_mismatch')],
            ]);
        }

        $tolerance = $this->fxValidationTolerance();
        $sourceAmountBase = round((float) $data['source_amount_base'], 4);
        $targetAmount = round((float) $data['target_amount'], 4);
        $fxRateToBase = round((float) $data['fx_rate_to_base'], 6);
        $targetAmountBase = round($targetAmount * $fxRateToBase, 4);
        $expectedFeeAmountBase = TreasuryFxConversionService::resolveFeeAmountBase(
            $sourceAmountBase,
            isset($data['fee_type']) ? (string) $data['fee_type'] : null,
            isset($data['fee_value']) ? (float) $data['fee_value'] : null,
            null
        );

        if (array_key_exists('fee_amount_base', $data) && $data['fee_amount_base'] !== null) {
            $submittedFeeAmountBase = round((float) $data['fee_amount_base'], 4);
            if (abs($submittedFeeAmountBase - $expectedFeeAmountBase) > $tolerance) {
                throw ValidationException::withMessages([
                    'fee_amount_base' => [trans('accounting::accounting.fx_hub.validation.fee_formula_mismatch')],
                ]);
            }
        }

        $expectedSourceAmountBase = round($targetAmountBase + $expectedFeeAmountBase, 4);
        if (abs($sourceAmountBase - $expectedSourceAmountBase) > $tolerance) {
            throw ValidationException::withMessages([
                'source_amount_base' => [trans('accounting::accounting.fx_hub.validation.source_formula_mismatch')],
            ]);
        }

        $data['target_currency_code'] = $targetCurrencyCode;
        $data['source_amount_base'] = $sourceAmountBase;
        $data['target_amount'] = $targetAmount;
        $data['fx_rate_to_base'] = $fxRateToBase;
        $data['target_amount_base'] = $targetAmountBase;
        $data['fee_amount_base'] = $expectedFeeAmountBase;

        $row = $service->create($data);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $row->id,
                'document_id' => (int) ($row->accounting_document_id ?? 0) ?: null,
            ],
        ]);
    }

    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    protected function fxValidationTolerance(): float
    {
        return 0.01;
    }
}

