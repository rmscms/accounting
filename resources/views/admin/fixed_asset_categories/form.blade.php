@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? config('app.name'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\FixedAssetCategory|null $category */
    $category = $category ?? null;
    $assetAccountOptions = $assetAccountOptions ?? [];
    $depreciationAccountOptions = $depreciationAccountOptions ?? [];
    $accumulatedAccountOptions = $accumulatedAccountOptions ?? [];
@endphp

<div class="container-fluid">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.fixed_asset_category_form.page_title_edit') : trans('accounting::accounting.fixed_asset_category_form.page_title_new') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.fixed_asset_category_form.page_subtitle') }}</small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.fixed-asset-categories.index') }}" class="btn btn-light btn-sm">
                    {{ trans('accounting::accounting.fixed_asset_category_form.back_to_list') }}
                </a>
                <a href="{{ route('admin.accounting.fixed-assets.index') }}" class="btn btn-outline-primary btn-sm">
                    {{ trans('accounting::accounting.fixed_asset_category_form.assets_manage') }}
                </a>
            </div>
        </div>
        <div class="card-body">
            @if($isEdit && $category)
                <form method="post" action="{{ route('admin.accounting.fixed-asset-categories.update', $category) }}" class="row g-3">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.accounting.fixed-asset-categories.store') }}" class="row g-3">
                    @csrf
            @endif

                <div class="col-12">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_category_form.section_identity') }}</h6>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset_category.name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $category->name ?? '') }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset_category.code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code', $category->code ?? '') }}" maxlength="50" required>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_category_form.code_hint') }}</div>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.common.description') }}</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description ?? '') }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_category_form.section_ledger') }}</h6>
                    <p class="small text-muted mb-2">{{ trans('accounting::accounting.fixed_asset_category_form.ledger_intro') }}</p>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset_category.asset_account') }}</label>
                    <select name="asset_account_id" class="form-select enhanced-select @error('asset_account_id') is-invalid @enderror">
                        <option value="">{{ trans('accounting::accounting.fixed_asset_category.select_account') }}</option>
                        @foreach($assetAccountOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('asset_account_id', $category->asset_account_id ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_category_form.asset_account_hint') }}</div>
                    @error('asset_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset_category.depreciation_account') }}</label>
                    <select name="depreciation_account_id" class="form-select enhanced-select @error('depreciation_account_id') is-invalid @enderror">
                        <option value="">{{ trans('accounting::accounting.fixed_asset_category.select_account') }}</option>
                        @foreach($depreciationAccountOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('depreciation_account_id', $category->depreciation_account_id ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_category_form.depreciation_account_hint') }}</div>
                    @error('depreciation_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.fixed_asset_category.accumulated_depreciation_account') }}</label>
                    <select name="accumulated_depreciation_account_id" class="form-select enhanced-select @error('accumulated_depreciation_account_id') is-invalid @enderror">
                        <option value="">{{ trans('accounting::accounting.fixed_asset_category.select_account') }}</option>
                        @foreach($accumulatedAccountOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('accumulated_depreciation_account_id', $category->accumulated_depreciation_account_id ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_category_form.accumulated_account_hint') }}</div>
                    @error('accumulated_depreciation_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">{{ trans('accounting::accounting.fixed_asset_category_form.section_status') }}</h6>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" id="fac-active" class="form-check-input" value="1"
                               @checked((bool) old('active', $category->active ?? true))>
                        <label class="form-check-label" for="fac-active">{{ trans('accounting::accounting.common.is_active') }}</label>
                    </div>
                    <div class="form-text">{{ trans('accounting::accounting.fixed_asset_category_form.active_hint') }}</div>
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.structured_resource_forms.save') }}</button>
                    <a href="{{ route('admin.accounting.fixed-asset-categories.index') }}" class="btn btn-light">{{ trans('accounting::accounting.structured_resource_forms.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'fixed-asset-category-help',
        'toggleLabel' => trans('accounting::accounting.fixed_asset_category_form_help.toggle_label'),
        'title' => trans('accounting::accounting.fixed_asset_category_form_help.title'),
        'paragraphs' => trans('accounting::accounting.fixed_asset_category_form_help.paragraphs'),
        'cardClass' => 'mt-3',
    ])
</div>
@endsection
