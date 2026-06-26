{{-- فرم اختصاصی نرخ مالیات — پکیج accounting --}}
@extends('cms::admin.layout.index')

@php
    $tr = 'accounting::accounting.tax_rates';
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\TaxRate|null $taxRate */
    $taxRate = $taxRate ?? null;
    $taxTypeOptions = $taxTypeOptions ?? [];
    $rateDefault = old('rate', $taxRate !== null ? (string) $taxRate->rate : '9');
    $oldCode = old('code', $taxRate?->code ?? '');
    $oldName = old('name', $taxRate?->name ?? '');
    $oldTaxType = old('tax_type', $taxRate?->tax_type ?? \RMS\Accounting\Models\TaxRate::TYPE_VAT);
    $oldIsDefault = (bool) old('is_default', $taxRate?->is_default ?? false);
    $oldActive = (bool) old('active', $taxRate?->active ?? true);
@endphp

@section('title', $isEdit ? trans($tr.'.pages.edit_title') : trans($tr.'.pages.create_title'))

@section('content')
<div class="container-fluid py-2" data-role="tax-rate-form-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <h4 class="mb-1 text-primary">
                                {{ $isEdit ? trans($tr.'.pages.edit_title') : trans($tr.'.pages.create_title') }}
                            </h4>
                            <p class="text-muted small mb-0">{{ trans($tr.'.pages.subtitle') }}</p>
                            @if($isEdit && $taxRate)
                                <p class="text-muted small mb-0 mt-1">
                                    <span class="font-monospace">#{{ $taxRate->id }}</span>
                                    — <span class="font-monospace">{{ $taxRate->code }}</span>
                                </p>
                            @endif
                        </div>
                        <a href="{{ route('admin.accounting.tax-rates.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">
                            <i class="ph-arrow-right me-1"></i>{{ trans($tr.'.pages.back') }}
                        </a>
                    </div>
                </div>

                <div class="card-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger mb-4">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($isEdit && $taxRate)
                        <form method="post" action="{{ route('admin.accounting.tax-rates.update', $taxRate) }}" class="needs-validation" novalidate>
                            @csrf
                            @method('PUT')
                    @else
                        <form method="post" action="{{ route('admin.accounting.tax-rates.store') }}" class="needs-validation" novalidate>
                            @csrf
                    @endif

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="rounded-3 border bg-light bg-opacity-50 p-4 h-100">
                                <h6 class="text-uppercase text-muted small fw-semibold mb-3">{{ trans($tr.'.section_main') }}</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium" for="tax_rate_code">
                                            {{ trans('accounting::accounting.fields.tax_code') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="code" id="tax_rate_code" maxlength="50" autocomplete="off"
                                               class="form-control font-monospace @error('code') is-invalid @enderror"
                                               value="{{ $oldCode }}" required>
                                        <div class="form-text">{{ trans($tr.'.hint_code') }}</div>
                                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium" for="tax_rate_name">
                                            {{ trans('accounting::accounting.fields.tax_name') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="name" id="tax_rate_name" maxlength="255"
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ $oldName }}" required>
                                        <div class="form-text">{{ trans($tr.'.hint_name') }}</div>
                                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium" for="tax_rate_type">
                                            {{ trans('accounting::accounting.fields.tax_type') }} <span class="text-danger">*</span>
                                        </label>
                                        <select name="tax_type" id="tax_rate_type" class="form-select @error('tax_type') is-invalid @enderror" required>
                                            @foreach($taxTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($oldTaxType === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">{{ trans($tr.'.hint_tax_type') }}</div>
                                        @error('tax_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium" for="tax_rate_rate">
                                            {{ trans('accounting::accounting.fields.tax_rate') }} (%) <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" name="rate" id="tax_rate_rate" step="0.01" min="0" max="100"
                                                   class="form-control @error('rate') is-invalid @enderror"
                                                   value="{{ $rateDefault }}" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="form-text">{{ trans($tr.'.hint_rate') }}</div>
                                        @error('rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="rounded-3 border p-4 h-100 bg-body-secondary bg-opacity-25">
                                <h6 class="text-uppercase text-muted small fw-semibold mb-3">{{ trans($tr.'.section_options') }}</h6>
                                <div class="d-flex flex-column gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_default" id="tax_rate_is_default" value="1"
                                               @checked($oldIsDefault)>
                                        <label class="form-check-label fw-medium" for="tax_rate_is_default">
                                            {{ trans('accounting::accounting.fields.is_default') }}
                                        </label>
                                        <div class="form-text mt-1">{{ trans($tr.'.hint_is_default') }}</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active" id="tax_rate_active" value="1"
                                               @checked($oldActive)>
                                        <label class="form-check-label fw-medium" for="tax_rate_active">
                                            {{ trans('accounting::accounting.fields.active') }}
                                        </label>
                                        <div class="form-text mt-1">{{ trans($tr.'.hint_active') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
                        <a href="{{ route('admin.accounting.tax-rates.index') }}" class="btn btn-light">{{ trans($tr.'.pages.back') }}</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ph-floppy-disk me-1"></i>{{ trans($tr.'.save') }}
                        </button>
                    </div>

                    </form>

                    @php
                        $helpParagraphs = trans($tr.'.help.paragraphs');
                        if (! is_array($helpParagraphs)) {
                            $helpParagraphs = [];
                        }
                    @endphp
                    @include('accounting::components.collapse_help_card', [
                        'toggleLabel' => trans($tr.'.help.toggle'),
                        'title' => trans($tr.'.help.title'),
                        'paragraphs' => $helpParagraphs,
                        'cardClass' => 'mt-4 border-primary border-opacity-25',
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
