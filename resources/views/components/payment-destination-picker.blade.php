@props([
    'context' => 'supplier_payment',
    'catalogUrl' => null,
    'namePrefix' => '',
    'initialPaymentMethodId' => null,
    'initialBankId' => null,
    'initialCashBoxId' => null,
    'initialChequeId' => null,
    'initialPosTerminalId' => null,
    'initialWalletId' => null,
    'setupRoutes' => [],
    'i18n' => [],
])
@php
    $catalogUrl = $catalogUrl ?? route('admin.accounting.ajax.payment-destinations', ['context' => $context]);
    $setupRoutes = is_array($setupRoutes) ? $setupRoutes : [];
    $i18nJson = json_encode(is_array($i18n) ? $i18n : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdpFieldId = static function (string $prefix, string $field): string {
        if ($prefix === '') {
            return 'fld-'.$field;
        }

        return 'fld-'.preg_replace('/\W+/', '_', rtrim($prefix, '.')).'_'.$field;
    };
@endphp
<style>
    .accounting-pdp .accounting-pdp-channels .nav-link {
        border-radius: 0.5rem;
        border: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
        color: var(--bs-body-color);
        padding: 0.5rem 0.75rem;
        min-height: 2.75rem;
    }
    .accounting-pdp .accounting-pdp-channels .nav-link:hover {
        background-color: var(--bs-tertiary-bg);
        border-color: var(--bs-border-color);
    }
    .accounting-pdp .accounting-pdp-channels .nav-link.active {
        border-color: var(--bs-primary);
        background-color: color-mix(in srgb, var(--bs-primary) 14%, transparent);
        color: var(--bs-primary);
        font-weight: 600;
    }
    .accounting-pdp .accounting-pdp-dest {
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    .accounting-pdp .accounting-pdp-dest:hover {
        border-color: var(--bs-primary) !important;
        background-color: var(--bs-tertiary-bg);
    }
    .accounting-pdp .accounting-pdp-dest.is-active {
        border-color: var(--bs-primary) !important;
        background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent);
        box-shadow: 0 0 0 1px var(--bs-primary);
    }
    .accounting-pdp .accounting-pdp-method label {
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .accounting-pdp .accounting-pdp-method label.accounting-pdp-method--on {
        border-color: var(--bs-primary) !important;
        background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent);
    }
</style>
<div class="payment-destination-picker accounting-pdp card border-0 shadow-sm rounded-3 overflow-hidden mb-0"
     data-payment-picker
     data-i18n="{{ e($i18nJson) }}"
     data-context="{{ e($context) }}"
     data-catalog-url="{{ e($catalogUrl) }}"
     data-initial-payment-method-id="{{ $initialPaymentMethodId !== null && $initialPaymentMethodId !== '' ? (int) $initialPaymentMethodId : '' }}"
     data-initial-bank-id="{{ $initialBankId !== null && $initialBankId !== '' ? (int) $initialBankId : '' }}"
     data-initial-cash-box-id="{{ $initialCashBoxId !== null && $initialCashBoxId !== '' ? (int) $initialCashBoxId : '' }}"
     data-initial-cheque-id="{{ $initialChequeId !== null && $initialChequeId !== '' ? (int) $initialChequeId : '' }}"
     data-initial-pos-terminal-id="{{ $initialPosTerminalId !== null && $initialPosTerminalId !== '' ? (int) $initialPosTerminalId : '' }}"
     data-initial-wallet-id="{{ $initialWalletId !== null && $initialWalletId !== '' ? (int) $initialWalletId : '' }}"
     data-setup-routes="{{ e(json_encode($setupRoutes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
    <div class="card-header border-0 py-3 px-3 px-lg-4 d-flex align-items-start gap-3 bg-body-secondary bg-opacity-50">
        <div class="flex-shrink-0 rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 3rem; height: 3rem;">
            <i class="ph-hand-coins fs-3" aria-hidden="true"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <h6 class="mb-1 fw-semibold">{{ trans('accounting::accounting.payment_destination.picker_title') }}</h6>
            <p class="mb-0 small text-muted">{{ trans('accounting::accounting.payment_destination.picker_subtitle') }}</p>
        </div>
    </div>
    <div class="card-body p-3 p-lg-4">
        <div class="d-none alert alert-danger border-0 shadow-sm mb-3" data-pdp-error role="alert"></div>
        <div class="d-flex align-items-center gap-2 text-body-secondary small mb-0 py-1" data-pdp-loading>
            <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
            <span>{{ trans('accounting::accounting.payment_destination.loading') }}</span>
        </div>
        <div class="d-none" data-pdp-main>
            <div class="mb-1">
                <span class="text-uppercase text-muted fw-semibold small" style="letter-spacing: .04em;">{{ trans('accounting::accounting.payment_destination.channel_label') }}</span>
            </div>
            <ul class="nav nav-pills accounting-pdp-channels flex-wrap mb-4" data-pdp-channels role="tablist"></ul>
            <div data-pdp-dest-wrap class="mb-4 d-none">
                <div class="mb-1">
                    <span class="text-uppercase text-muted fw-semibold small" style="letter-spacing: .04em;">{{ trans('accounting::accounting.payment_destination.destination_label') }}</span>
                </div>
                <div class="row row-cols-1 row-cols-md-2 g-2" data-pdp-destinations></div>
            </div>
            <div data-pdp-method-wrap class="mb-0 d-none">
                <div class="mb-1">
                    <span class="text-uppercase text-muted fw-semibold small" style="letter-spacing: .04em;">{{ trans('accounting::accounting.payment_destination.method_label') }}</span>
                </div>
                <div class="d-flex flex-column gap-2 accounting-pdp-method" data-pdp-methods></div>
            </div>
            <div class="d-none mt-3" data-pdp-empty>
                <div class="alert alert-warning border-0 shadow-sm small mb-3" data-pdp-empty-msg role="status"></div>
                <div class="d-flex flex-wrap gap-2" data-pdp-empty-links></div>
            </div>
        </div>
    </div>
    <input type="hidden" name="{{ $namePrefix }}payment_method_id" id="{{ $pdpFieldId($namePrefix, 'payment_method_id') }}" data-pdp-field="payment_method_id" value="">
    <input type="hidden" name="{{ $namePrefix }}bank_id" id="{{ $pdpFieldId($namePrefix, 'bank_id') }}" data-pdp-field="bank_id" value="">
    <input type="hidden" name="{{ $namePrefix }}cash_box_id" id="{{ $pdpFieldId($namePrefix, 'cash_box_id') }}" data-pdp-field="cash_box_id" value="">
    <input type="hidden" name="{{ $namePrefix }}cheque_id" id="{{ $pdpFieldId($namePrefix, 'cheque_id') }}" data-pdp-field="cheque_id" value="">
    <input type="hidden" name="{{ $namePrefix }}pos_terminal_id" id="{{ $pdpFieldId($namePrefix, 'pos_terminal_id') }}" data-pdp-field="pos_terminal_id" value="">
    <input type="hidden" name="{{ $namePrefix }}wallet_id" id="{{ $pdpFieldId($namePrefix, 'wallet_id') }}" data-pdp-field="wallet_id" value="">
</div>
