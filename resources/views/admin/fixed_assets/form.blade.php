@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? config('app.name'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\FixedAsset|null $asset */
    $asset = $asset ?? null;
    $categoryOptions = $categoryOptions ?? [];
    /** @var \RMS\Accounting\Models\AccountingDocument|null $purchaseDocument */
    $purchaseDocument = $purchaseDocument ?? null;
    /** @var \RMS\Accounting\Models\DepreciationSchedule|null $nextDepreciationSchedule */
    $nextDepreciationSchedule = $nextDepreciationSchedule ?? null;
    /** @var \RMS\Accounting\Models\DepreciationEntry|null $lastDepreciationEntry */
    $lastDepreciationEntry = $lastDepreciationEntry ?? null;

    $amountDecimalPlaces = (int) ($amountDecimalPlaces ?? 0);
    $formatAmount = static function ($value) use ($amountDecimalPlaces): string {
        return number_format((float) $value, $amountDecimalPlaces, '.', ',');
    };

    $purchaseDateValue = old('purchase_date');
    if ($purchaseDateValue === null || trim((string) $purchaseDateValue) === '') {
        if ($isEdit && $asset && $asset->purchase_date) {
            $purchaseDateValue = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(
                $asset->purchase_date instanceof \DateTimeInterface
                    ? $asset->purchase_date->format('Y-m-d')
                    : (string) $asset->purchase_date
            );
        } else {
            $purchaseDateValue = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
        }
    }

    $statusLabel = null;
    if ($isEdit && $asset) {
        $statusKey = (string) ($asset->status ?? '');
        $statusLabel = trans('accounting::accounting.fixed_asset.statuses.'.$statusKey);
        if ($statusLabel === 'accounting::accounting.fixed_asset.statuses.'.$statusKey) {
            $statusLabel = $statusKey;
        }
    }
@endphp

<div class="container-fluid">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.fixed_asset_form.page_title_edit') : trans('accounting::accounting.fixed_asset_form.page_title_new') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.fixed_asset_form.page_subtitle') }}</small>
                @if($isEdit && $asset)
                    <small class="text-muted d-block mt-1">
                        {{ trans('accounting::accounting.fixed_asset.code') }}:
                        <span class="font-monospace">{{ $asset->asset_code ?: ('#'.$asset->id) }}</span>
                        @if($statusLabel)
                            — {{ trans('accounting::accounting.common.status') }}: <span class="badge bg-secondary">{{ $statusLabel }}</span>
                        @endif
                    </small>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.fixed-assets.index') }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.fixed_asset_form.back_to_list') }}</a>
                <a href="{{ route('admin.accounting.fixed-asset-categories.index') }}" class="btn btn-outline-primary btn-sm">{{ trans('accounting::accounting.fixed_asset_form.categories_manage') }}</a>
            </div>
        </div>

        <div class="card-body">
            @if(empty($categoryOptions))
                <div class="alert alert-warning">{{ trans('accounting::accounting.fixed_asset_form.no_categories') }}</div>
            @endif

            @if($isEdit && $asset)
                <form method="post" action="{{ route('admin.accounting.fixed-assets.update', $asset) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="stay_in_form" value="0">
            @else
                <form method="post" action="{{ route('admin.accounting.fixed-assets.store') }}" class="row g-3">
                    @csrf
            @endif

                <div class="col-12 mt-1">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_form.section_identity') }}</h6>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.code') }}</label>
                    <input type="text" name="asset_code" class="form-control @error('asset_code') is-invalid @enderror"
                           value="{{ old('asset_code', $asset->asset_code ?? '') }}" placeholder="{{ trans('accounting::accounting.fixed_asset.code_auto_placeholder') }}">
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.asset_code_hint') }}</div>
                    @error('asset_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $asset->name ?? '') }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <x-accounting::date-field
                    name="purchase_date"
                    :label="trans('accounting::accounting.fixed_asset.purchase_date') . ' <span class=\'text-danger\'>*</span>'"
                    :value="$purchaseDateValue"
                    :required="true"
                    col-class="col-md-4"
                    error-key="purchase_date"
                />

                <div class="col-md-8">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.category') }} <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select enhanced-select @error('category_id') is-invalid @enderror" required>
                        <option value="">{{ trans('accounting::accounting.fixed_asset.select_category') }}</option>
                        @foreach($categoryOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('category_id', $asset->category_id ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_form.section_financial') }}</h6>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.purchase_price') }} <span class="text-danger">*</span></label>
                    <input type="text" name="purchase_price" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                           class="form-control amount-decimal @error('purchase_price') is-invalid @enderror"
                           value="{{ old('purchase_price', $isEdit && $asset ? $formatAmount($asset->purchase_price) : '') }}" required>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.purchase_price_hint') }}</div>
                    @error('purchase_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.salvage_value') }}</label>
                    <input type="text" name="salvage_value" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                           class="form-control amount-decimal @error('salvage_value') is-invalid @enderror"
                           value="{{ old('salvage_value', $isEdit && $asset ? $formatAmount($asset->salvage_value) : $formatAmount(0)) }}">
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.salvage_hint') }}</div>
                    @error('salvage_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.useful_life_years') }} <span class="text-danger">*</span></label>
                    <input type="number" name="useful_life_years" class="form-control @error('useful_life_years') is-invalid @enderror"
                           value="{{ old('useful_life_years', $asset->useful_life_years ?? 5) }}" min="1" max="200" step="1" required>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.useful_life_hint') }}</div>
                    @error('useful_life_years')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-12">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.depreciation_method') }} <span class="text-danger">*</span></label>
                    <select name="depreciation_method" class="form-select @error('depreciation_method') is-invalid @enderror" required>
                        <option value="straight_line" @selected(old('depreciation_method', $asset->depreciation_method ?? 'straight_line') === 'straight_line')>{{ trans('accounting::accounting.fixed_asset.method_straight_line') }}</option>
                        <option value="declining_balance" @selected(old('depreciation_method', $asset->depreciation_method ?? '') === 'declining_balance')>{{ trans('accounting::accounting.fixed_asset.method_declining_balance') }}</option>
                        <option value="units_of_production" @selected(old('depreciation_method', $asset->depreciation_method ?? '') === 'units_of_production')>{{ trans('accounting::accounting.fixed_asset.method_units_of_production') }}</option>
                    </select>
                    @error('depreciation_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="border rounded p-3 bg-light bg-opacity-50">
                        <h6 class="mb-2">{{ trans('accounting::accounting.fixed_asset.payment_account_id') }}</h6>
                        <p class="small text-muted mb-3">{{ trans('accounting::accounting.fixed_asset_form.depreciation_payment_hint') }}</p>
                        @error('payment_method_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        @error('bank_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        @error('cash_box_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        @error('pos_terminal_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        @error('wallet_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                        <x-accounting::payment-destination-picker
                            context="supplier_payment"
                            :catalog-url="route('admin.accounting.ajax.payment-destinations', ['context' => 'supplier_payment'])"
                            :initial-payment-method-id="(int) old('payment_method_id', 0)"
                            :initial-bank-id="(int) old('bank_id', 0)"
                            :initial-cash-box-id="(int) old('cash_box_id', 0)"
                            :initial-cheque-id="0"
                            :initial-pos-terminal-id="(int) old('pos_terminal_id', 0)"
                            :initial-wallet-id="(int) old('wallet_id', 0)"
                            :setup-routes="[
                                'banks' => route('admin.accounting.banks.index'),
                                'cashboxes' => route('admin.accounting.cashboxes.index'),
                                'cheques' => route('admin.accounting.cheques.index'),
                                'pos-terminals' => route('admin.accounting.pos-terminals.index'),
                                'wallets' => route('admin.accounting.wallets.index'),
                                'payment-methods' => route('admin.accounting.payment-methods.index'),
                            ]"
                        />
                    </div>
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_form.section_extra') }}</h6>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.location') }}</label>
                    <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                           value="{{ old('location', $asset->location ?? '') }}">
                    @error('location')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.serial_number') }}</label>
                    <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                           value="{{ old('serial_number', $asset->serial_number ?? '') }}">
                    @error('serial_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.common.description') }}</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $asset->description ?? '') }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset.notes') }}</label>
                    <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $asset->notes ?? '') }}</textarea>
                    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                @if($isEdit && $asset)
                    <div class="col-12">
                        @if($purchaseDocument)
                            <div class="alert alert-primary border d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                                <div>
                                    <strong>سند خرید دارایی ثبت شده است.</strong>
                                    <div class="small text-muted">شماره سند: {{ $purchaseDocument->document_number }}</div>
                                </div>
                                <a href="{{ route('admin.accounting.documents.show', ['document' => $purchaseDocument->id]) }}" class="btn btn-sm btn-primary">
                                    مشاهده سند
                                </a>
                            </div>
                        @else
                            <div class="alert alert-warning border mb-0">
                                <strong>سند خرید این دارایی هنوز ثبت نشده است.</strong>
                                <div class="small">بعد از انتخاب مقصد پرداخت، دکمه ثبت سند خرید را بزنید.</div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary" @disabled(!$isEdit && empty($categoryOptions))>
                        {{ trans('accounting::accounting.structured_resource_forms.save') }}
                    </button>
                    @if($isEdit && $asset && ! $purchaseDocument)
                        <button type="submit" class="btn btn-success" name="submit_action" value="save_and_post_document">
                            ذخیره و ثبت سند خرید
                        </button>
                    @endif
                    <a href="{{ route('admin.accounting.fixed-assets.index') }}" class="btn btn-light">{{ trans('accounting::accounting.structured_resource_forms.cancel') }}</a>
                </div>
            </form>

            @if($isEdit && $asset)
                <hr class="my-4">
                <div class="border rounded p-3 bg-light bg-opacity-50">
                    <h6 class="mb-1">{{ trans('accounting::accounting.fixed_asset_form.depreciation_section_title') }}</h6>
                    <p class="small text-muted mb-3">{{ trans('accounting::accounting.fixed_asset_form.depreciation_section_subtitle') }}</p>

                    @if($lastDepreciationEntry && $lastDepreciationEntry->accountingDocument)
                        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>{{ trans('accounting::accounting.fixed_asset_form.depreciation_last_document_body', ['document' => $lastDepreciationEntry->accountingDocument->document_number]) }}</div>
                            <a href="{{ route('admin.accounting.documents.show', ['document' => $lastDepreciationEntry->accountingDocument->id]) }}" class="btn btn-sm btn-outline-primary">
                                {{ trans('accounting::accounting.fixed_asset_form.depreciation_last_document_btn') }}
                            </a>
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.accounting.fixed-assets.record-depreciation', ['id' => $asset->id]) }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">{{ trans('accounting::accounting.fixed_asset_form.depreciation_period_date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="period_date" class="form-control persian-datepicker @error('period_date') is-invalid @enderror"
                                   data-format="YYYY/MM/DD"
                                   value="{{ old('period_date', $nextDepreciationSchedule && $nextDepreciationSchedule->period_date ? \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay($nextDepreciationSchedule->period_date->format('Y-m-d')) : '') }}"
                                   required>
                            @if($nextDepreciationSchedule && $nextDepreciationSchedule->period_date)
                                <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.depreciation_next_period_hint', ['date' => \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay($nextDepreciationSchedule->period_date->format('Y-m-d'))]) }}</div>
                            @endif
                            @error('period_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ trans('accounting::accounting.fixed_asset_form.depreciation_amount') }}</label>
                            <input type="text" name="depreciation_amount" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                                   class="form-control amount-decimal @error('depreciation_amount') is-invalid @enderror"
                                   value="{{ old('depreciation_amount', $nextDepreciationSchedule ? $formatAmount($nextDepreciationSchedule->depreciation_amount ?? 0) : '') }}">
                            <div class="form-text">{{ trans('accounting::accounting.fixed_asset_form.depreciation_amount_hint') }}</div>
                            @error('depreciation_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                {{ trans('accounting::accounting.fixed_asset_form.depreciation_submit_btn') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'fixed-asset-form-help',
        'toggleLabel' => trans('accounting::accounting.fixed_asset_form_help.toggle_label'),
        'title' => trans('accounting::accounting.fixed_asset_form_help.title'),
        'paragraphs' => trans('accounting::accounting.fixed_asset_form_help.paragraphs'),
        'cardClass' => 'mt-3',
    ])
</div>

@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
