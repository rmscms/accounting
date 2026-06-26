{{-- فرم اختصاصی بانک — جستجوی Ajax حساب دفترکل (مثل هزینه‌ها) --}}
@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? config('app.name'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\Bank|null $bank */
    $bank = $bank ?? null;
    $selectedAccount = $selectedAccount ?? null;
    $accountSearchUrl = $accountSearchUrl ?? route('admin.accounting.banks.search-accounts');
    $initialLedgerBranches = $initialLedgerBranches ?? collect();
    $autoCreateAccountEnabled = isset($autoCreateAccountEnabled)
        ? (bool) $autoCreateAccountEnabled
        : true;
    $selectedId = $selectedAccount?->id;
    $branchIds = $initialLedgerBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
    $selectedInBranches = $selectedId && in_array((int) $selectedId, $branchIds, true);
@endphp

<div class="container-fluid" data-role="bank-form-page">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.bank_form.title_edit') : trans('accounting::accounting.bank_form.title') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.bank_form.page_subtitle') }}</small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.banks.index') }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.bank_form.back_to_list') }}</a>
            </div>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($isEdit && $bank)
                <form method="post" action="{{ route('admin.accounting.banks.update', $bank) }}" class="row g-3">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.accounting.banks.store') }}" class="row g-3">
                    @csrf
            @endif

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $bank?->name) }}" required maxlength="255" autocomplete="organization">
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.short_name') }}</label>
                    <input type="text" name="short_name" class="form-control @error('short_name') is-invalid @enderror"
                           value="{{ old('short_name', $bank?->short_name) }}" maxlength="255" autocomplete="off">
                    <div class="form-text">{{ trans('accounting::accounting.bank.short_name_hint') }}</div>
                    @error('short_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.branch_name') }}</label>
                    <input type="text" name="branch_name" class="form-control @error('branch_name') is-invalid @enderror"
                           value="{{ old('branch_name', $bank?->branch_name) }}" maxlength="255">
                    @error('branch_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.account_number') }} <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" class="form-control @error('account_number') is-invalid @enderror"
                           value="{{ old('account_number', $bank?->account_number) }}" required maxlength="50" autocomplete="off">
                    @error('account_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.iban') }}</label>
                    <input type="text" name="iban" class="form-control @error('iban') is-invalid @enderror"
                           value="{{ old('iban', $bank?->iban) }}" maxlength="50" dir="ltr" autocomplete="off">
                    @error('iban')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank.swift') }}</label>
                    <input type="text" name="swift_code" class="form-control @error('swift_code') is-invalid @enderror"
                           value="{{ old('swift_code', $bank?->swift_code) }}" maxlength="20" dir="ltr" autocomplete="off">
                    @error('swift_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <input type="hidden" name="auto_create_account" value="0">
                    <div class="form-check form-switch">
                        <input type="checkbox"
                               name="auto_create_account"
                               id="bank-auto-create-account"
                               class="form-check-input js-treasury-auto-create-toggle @error('auto_create_account') is-invalid @enderror"
                               value="1"
                               @checked($autoCreateAccountEnabled)>
                        <label class="form-check-label" for="bank-auto-create-account">
                            {{ trans('accounting::accounting.treasury_sub_accounts.toggle_label') }}
                        </label>
                    </div>
                    <div class="form-text">{{ trans('accounting::accounting.treasury_sub_accounts.bank_hint') }}</div>
                    @error('auto_create_account')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12" data-bank-manual-account-wrap>
                    <label class="form-label">{{ trans('accounting::accounting.bank.account_id') }} <span class="text-danger">*</span></label>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1" for="bank-account-main">{{ trans('accounting::accounting.bank_form.main_branch_label') }}</label>
                            <select name="account_id" id="bank-account-main" class="form-select @error('account_id') is-invalid @enderror" required>
                                <option value="">{{ trans('accounting::accounting.bank_form.main_branch_placeholder') }}</option>
                                @foreach ($initialLedgerBranches as $acc)
                                    <option value="{{ $acc->id }}" @selected((int) $selectedId === (int) $acc->id)>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                @endforeach
                                @if ($selectedAccount && ! $selectedInBranches && $selectedId)
                                    <option value="{{ $selectedAccount->id }}" selected>{{ $selectedAccount->code }} — {{ $selectedAccount->name }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1" for="bank-account-detail-search">{{ trans('accounting::accounting.bank_form.detail_search_label') }}</label>
                            <select id="bank-account-detail-search" class="form-select bank-account-detail-search"
                                    data-search-url="{{ e($accountSearchUrl) }}"
                                    data-placeholder="{{ e(trans('accounting::accounting.bank_form.detail_search_placeholder')) }}">
                                <option value=""></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text">{{ trans('accounting::accounting.treasury_sub_accounts.manual_account_hint') }}</div>
                    @error('account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <input type="hidden" name="active" value="0">
                    <div class="form-check">
                        @php
                            $activeOld = old('active', ($bank?->active ?? true) ? '1' : '0');
                        @endphp
                        <input type="checkbox" name="active" id="bank-active" class="form-check-input" value="1"
                               @checked($activeOld === '1' || $activeOld === 1 || $activeOld === true)>
                        <label class="form-check-label" for="bank-active">{{ trans('accounting::accounting.bank.is_active') }}</label>
                    </div>
                    @error('active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.bank_form.save') }}</button>
                    <a href="{{ route('admin.accounting.banks.index') }}" class="btn btn-light">{{ trans('accounting::accounting.bank_form.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'accounting-bank-form-help',
        'toggleLabel' => trans('accounting::accounting.bank_form_help.toggle_label'),
        'title' => trans('accounting::accounting.bank_form_help.title'),
        'paragraphs' => trans('accounting::accounting.bank_form_help.paragraphs'),
        'cardClass' => 'mt-3',
    ])
</div>
@endsection

@push('scripts')
<script>
(function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
        return;
    }
    var $main = jQuery('#bank-account-main');
    var $detail = jQuery('#bank-account-detail-search');
    if (!$main.length || !$detail.length) return;

    var searchUrl = $detail.data('search-url');
    var placeholder = $detail.data('placeholder') || '';

    $detail.select2({
        width: '100%',
        dir: 'rtl',
        placeholder: placeholder,
        allowClear: true,
        minimumInputLength: 2,
        language: {
            searching: function () {
                return @json(trans('accounting::accounting.bank_form.account_searching'));
            },
            inputTooShort: function () {
                return @json(trans('accounting::accounting.bank_form.detail_search_placeholder'));
            },
            noResults: function () {
                return @json(trans('accounting::accounting.bank_form.account_no_results'));
            }
        },
        ajax: {
            url: searchUrl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: (params.term != null ? params.term : '') || '' };
            },
            processResults: function (data) {
                return { results: data.results || [] };
            },
            cache: true
        }
    });

    $detail.on('select2:select', function (e) {
        var d = e.params.data;
        var id = String(d.id);
        var text = d.text || id;
        var exists = false;
        $main.find('option').each(function () {
            if (String(this.value) === id) {
                exists = true;
                return false;
            }
        });
        if (!exists) {
            $main.append(new Option(text, id, true, true));
        } else {
            $main.val(id);
        }
        $main.trigger('change');
    });

    $main.on('change', function () {
        $detail.val(null).trigger('change');
    });
})();
</script>
@endpush
