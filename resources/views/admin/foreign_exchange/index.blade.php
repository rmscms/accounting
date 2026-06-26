@extends('cms::admin.layout.index')

@section('content')
<div class="container-fluid fx-hub-page"
     data-fx-store-url="{{ (string) ($fxConversionStoreUrl ?? '') }}"
     data-base-currency="{{ strtoupper((string) ($baseCurrencyCode ?? 'IRT')) }}"
     data-amount-decimals="{{ (int) ($amountDecimalPlaces ?? 4) }}"
     data-target-decimals="2">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h5 class="mb-1"><i class="ph-arrows-left-right me-1 text-primary"></i>مرکز تبدیل ارز</h5>
                <p class="text-muted mb-0">
                    تامین مالی ارزی را از بانک/صندوق انجام دهید، به کیف پول ارزی منتقل کنید و با ثبت کارمزد، سند دقیق حسابداری بسازید.
                </p>
            </div>
            <div class="d-flex gap-2">
                @if(!empty($currenciesManageUrl))
                    <a href="{{ $currenciesManageUrl }}" class="btn btn-light btn-sm"><i class="ph-coins me-1"></i>مدیریت ارزها</a>
                @endif
                @if(!empty($walletReportUrl))
                    <a href="{{ $walletReportUrl }}" class="btn btn-outline-success btn-sm"><i class="ph-wallet me-1"></i>گزارش کیف پول</a>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100 fx-hub-card">
                <div class="card-header fw-semibold">۱) منبع تامین مالی</div>
                <div class="card-body">
                    <label class="form-label">حساب بانکی مبدا</label>
                    <select class="form-select mb-3" data-field="source_bank_id">
                        <option value="">انتخاب بانک</option>
                        @foreach(($banks ?? collect()) as $bank)
                            <option value="{{ (int) $bank->id }}">{{ (string) $bank->name }}</option>
                        @endforeach
                    </select>

                    <label class="form-label">صندوق مبدا</label>
                    <select class="form-select mb-3" data-field="source_cash_box_id">
                        <option value="">انتخاب صندوق</option>
                        @foreach(($cashBoxes ?? collect()) as $cash)
                            <option value="{{ (int) $cash->id }}">{{ (string) $cash->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">یکی از بانک یا صندوق را انتخاب کنید.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100 fx-hub-card">
                <div class="card-header fw-semibold">۲) تبدیل ارز</div>
                <div class="card-body">
                    <label class="form-label">ارز مبدا (پایه)</label>
                    <input class="form-control mb-3" data-field="source_currency_code" value="{{ (string) ($baseCurrencyCode ?? 'IRT') }}" readonly>

                    <label class="form-label d-flex justify-content-between">
                        <span>ارز مقصد</span>
                        @if(!empty($currenciesManageUrl))
                            <a href="{{ $currenciesManageUrl }}" class="small">افزودن/ویرایش ارز</a>
                        @endif
                    </label>
                    <select class="form-select mb-3" data-field="target_currency_code">
                        <option value="">انتخاب ارز مقصد</option>
                        @foreach(($currencies ?? collect()) as $currency)
                            <option value="{{ strtoupper((string) $currency->code) }}" @selected(strtoupper((string) $currency->code) === 'USD')>
                                {{ strtoupper((string) $currency->code) }} - {{ (string) ($currency->name ?? $currency->code) }}
                            </option>
                        @endforeach
                    </select>

                    <label class="form-label">مبلغ ارز مقصد</label>
                    <input class="form-control mb-3 amount-decimal"
                           data-field="target_amount"
                           data-type="amount-decimal"
                           data-decimals="2"
                           value="0">

                    <label class="form-label">نرخ تبدیل به ارز پایه</label>
                    <input class="form-control mb-3 amount-decimal"
                           data-field="fx_rate"
                           data-type="amount-decimal"
                           data-decimals="6"
                           value="1">

                    <label class="form-label">مبلغ خروجی از منبع (ارز پایه)</label>
                    <input class="form-control amount-decimal"
                           data-field="source_amount_base"
                           data-type="amount-decimal"
                           data-decimals="{{ (int) ($amountDecimalPlaces ?? 4) }}"
                           value="0">
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100 fx-hub-card">
                <div class="card-header fw-semibold">۳) کیف پول مقصد و کارمزد</div>
                <div class="card-body">
                    <label class="form-label d-flex justify-content-between">
                        <span>کیف پول مقصد</span>
                        <span class="d-flex gap-2">
                            @if(!empty($walletCreateUrl))
                                <a href="{{ $walletCreateUrl }}" class="small">ایجاد کیف پول جدید</a>
                            @endif
                            @if(!empty($walletReportUrl))
                                <a href="{{ $walletReportUrl }}" class="small">گزارش کیف پول</a>
                            @endif
                        </span>
                    </label>
                    <select class="form-select mb-2" data-field="target_wallet_id">
                        <option value="">انتخاب کیف پول</option>
                        @foreach(($wallets ?? collect()) as $wallet)
                            <option value="{{ (int) $wallet->id }}" data-currency="{{ strtoupper((string) ($wallet->currency_code ?? '')) }}">
                                Wallet #{{ (int) $wallet->id }} ({{ (string) ($wallet->wallet_type ?? '-') }} / {{ (string) ($wallet->currency_code ?? '-') }})
                            </option>
                        @endforeach
                    </select>
                    @if(($wallets ?? collect())->isEmpty())
                        <div class="alert alert-warning py-2 mt-2">
                            کیف پول فعالی یافت نشد.
                            @if(!empty($walletCreateUrl))
                                <a href="{{ $walletCreateUrl }}" class="alert-link">ایجاد کیف پول</a>
                            @elseif(!empty($walletReportUrl))
                                <a href="{{ $walletReportUrl }}" class="alert-link">مشاهده گزارش کیف پول</a>
                            @endif
                        </div>
                    @endif

                    <label class="form-label mt-2">نوع کارمزد</label>
                    <select class="form-select mb-2" data-field="fee_type">
                        <option value="fixed">مبلغ ثابت</option>
                        <option value="percent">درصدی</option>
                    </select>

                    <label class="form-label" data-role="fee-value-label">مقدار کارمزد (مبلغ ثابت)</label>
                    <input class="form-control mb-2 amount-decimal"
                           data-field="fee_value"
                           data-type="amount-decimal"
                           data-decimals="{{ (int) ($amountDecimalPlaces ?? 4) }}"
                           value="0"
                           placeholder="مثلاً 250000 یا 1.5">

                    <label class="form-label">مبلغ نهایی کارمزد (ارز پایه)</label>
                    <input class="form-control mb-2 amount-decimal"
                           data-field="fee_amount"
                           data-type="amount-decimal"
                           data-decimals="{{ (int) ($amountDecimalPlaces ?? 4) }}"
                           value="0"
                           readonly>
                    <div class="form-text mb-2">
                        فرمول کنترل: مبلغ منبع = (مبلغ مقصد × نرخ) + کارمزد
                    </div>
                    <div class="alert alert-warning py-2 px-3 mb-3 d-none" data-role="fx-mismatch"></div>

                    <label class="form-label">یادداشت</label>
                    <textarea class="form-control mb-3" data-field="notes" rows="2"></textarea>

                    <button type="button" class="btn btn-primary w-100" data-action="fx-conversion-save">
                        ثبت تبدیل
                    </button>
                    <div class="alert alert-info py-2 px-3 mt-2 mb-0 d-none" data-role="fx-status"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

