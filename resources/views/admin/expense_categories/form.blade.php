@extends('cms::admin.layout.index')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('admin.accounting.expense-categories.index') }}" class="btn btn-light btn-sm me-2">
            <i class="ph-arrow-right"></i>
        </a>
        <h4 class="mb-0">{{ $pageTitle }}</h4>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post"
                  action="{{ $isEdit ? route('admin.accounting.expense-categories.update', $model) : route('admin.accounting.expense-categories.store') }}"
                  id="expense-category-form">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="expense-category-code">{{ trans('accounting::accounting.fields.category_code') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               name="code"
                               id="expense-category-code"
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $isEdit ? $model->code : ($suggestedCode ?? '')) }}"
                               autocomplete="off"
                               data-expense-category-code-input>
                        <div class="invalid-feedback d-block" data-expense-category-code-feedback style="min-height: 1.25rem;"></div>
                        @error('code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1">
                            {{ trans('accounting::accounting.expense_category_form.code_hint', [
                                'prefix' => $expenseCategoryConfig['code_prefix'] ?? '',
                                'separator' => $expenseCategoryConfig['code_separator'] ?? '-',
                            ]) }}
                        </small>
                        @if(!$isEdit && !empty($suggestedCode))
                            <small class="text-muted d-block">
                                {{ trans('accounting::accounting.expense_category_form.suggested_code', ['code' => $suggestedCode]) }}
                            </small>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="expense-category-name">{{ trans('accounting::accounting.fields.category_name') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               id="expense-category-name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $isEdit ? $model->name : '') }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="expense-category-parent">{{ trans('accounting::accounting.fields.parent_category') }}</label>
                        @php
                            $parentVal = old('parent_id', $isEdit ? $model->parent_id : null);
                            if ($parentVal === null) {
                                $parentVal = '';
                            }
                        @endphp
                        <select name="parent_id" id="expense-category-parent" class="form-select @error('parent_id') is-invalid @enderror">
                            @foreach($parentOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $parentVal === (string) $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">{{ trans('accounting::accounting.expense_category_form.parent_help') }}</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="expense-category-account">{{ trans('accounting::accounting.fields.expense_account') }} <span class="text-danger">*</span></label>
                        <select name="account_id" id="expense-category-account" class="form-select @error('account_id') is-invalid @enderror" required>
                            <option value="">{{ trans('accounting::accounting.expense_category_form.select_account') }}</option>
                            @foreach($accountOptions as $aid => $alabel)
                                <option value="{{ $aid }}" @selected((string) old('account_id', $isEdit ? $model->account_id : '') === (string) $aid)>
                                    {{ $alabel }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="expense-category-description">{{ trans('accounting::accounting.fields.description') }}</label>
                        <textarea name="description"
                                  id="expense-category-description"
                                  class="form-control @error('description') is-invalid @enderror"
                                  rows="3">{{ old('description', $isEdit ? $model->description : '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox"
                                   name="active"
                                   id="expense-category-active"
                                   class="form-check-input"
                                   value="1"
                                   @checked(old('active', $isEdit ? $model->active : true))>
                            <label class="form-check-label" for="expense-category-active">{{ trans('accounting::accounting.fields.active') }}</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.expense_category_form.save') }}</button>
                    <a href="{{ route('admin.accounting.expense-categories.index') }}" class="btn btn-light">{{ trans('accounting::accounting.expense_category_form.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'expense-category-help-collapse',
        'toggleLabel' => trans('accounting::accounting.expense_category_help.toggle_label'),
        'title' => trans('accounting::accounting.expense_category_help.title'),
        'paragraphs' => trans('accounting::accounting.expense_category_help.paragraphs'),
    ])
</div>
@endsection
