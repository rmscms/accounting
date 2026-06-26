@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? trans('accounting::accounting.currency.title'))

@section('content')
<div class="container-fluid">
    <div class="card border-0 shadow-sm border-start border-4 border-info border-opacity-50">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="mb-0">{{ $isEdit ? trans('accounting::accounting.currency.edit') : trans('accounting::accounting.currency.create') }}</h5>
            <a href="{{ route('admin.accounting.currencies.index') }}" class="btn btn-light btn-sm">
                <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_resource_forms.back_to_list') }}
            </a>
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

            @if($isEdit)
                <form method="post" action="{{ route('admin.accounting.currencies.update', ['currency' => $currency->code]) }}" class="row g-3">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.accounting.currencies.store') }}" class="row g-3">
                    @csrf
            @endif
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.currency.code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code', (string) ($currency->code ?? '')) }}" maxlength="10" required>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label">{{ trans('accounting::accounting.currency.name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', (string) ($currency->name ?? '')) }}" maxlength="100" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.currency.symbol') }}</label>
                    <input type="text" name="symbol" class="form-control @error('symbol') is-invalid @enderror"
                           value="{{ old('symbol', (string) ($currency->symbol ?? '')) }}" maxlength="10">
                    @error('symbol')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.currency.decimal_places') }} <span class="text-danger">*</span></label>
                    <input type="number" name="decimals" class="form-control @error('decimals') is-invalid @enderror"
                           value="{{ old('decimals', (int) ($currency->decimals ?? 0)) }}" min="0" max="6" step="1" required>
                    @error('decimals')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.currency.is_base') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_base" value="0">
                        <input class="form-check-input" type="checkbox" name="is_base" value="1" @checked(old('is_base', (bool) ($currency->is_base ?? false)))>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.currency.is_reference') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_reference" value="0">
                        <input class="form-check-input" type="checkbox" name="is_reference" value="1" @checked(old('is_reference', (bool) ($currency->is_reference ?? false)))>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.currency.is_active') }}</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', (bool) ($currency->active ?? true)))>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.accounting.currencies.index') }}" class="btn btn-light">
                        {{ trans('accounting::accounting.common.back') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-floppy-disk me-1"></i>{{ trans('accounting::accounting.structured_resource_forms.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

