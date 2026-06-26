@props([
    'name' => 'customer_id',
    'id' => 'fld-customer_id',
    'label' => null,
    'required' => false,
    'searchUrl' => null,
    'createUrl' => null,
    'selectedId' => null,
    'selectedCustomerId' => null,
    'selectedText' => '',
    'typeOptions' => [],
    'currencyOptions' => [],
    'defaultCurrency' => 'IRT',
])
@php
    $pickerId = 'sales-customer-picker-'.md5((string) $id.'|'.(string) $name);
    $modalId = $pickerId.'-modal';
    $searchUrl = $searchUrl ?: route('admin.accounting.customer-invoices.search-customers');
    $createUrl = $createUrl ?: route('admin.accounting.customer-invoices.quick-create-customer');
    $selectedId = ($selectedId !== null && $selectedId !== '') ? (string) $selectedId : '';
    $selectedCustomerId = ($selectedCustomerId !== null && $selectedCustomerId !== '') ? (string) $selectedCustomerId : $selectedId;
    $selectedText = trim((string) $selectedText);
    $typeOptions = is_array($typeOptions) && $typeOptions !== [] ? $typeOptions : [
        'regular' => trans('accounting::accounting.customer.type_regular'),
        'vip' => trans('accounting::accounting.customer.type_vip'),
        'occasional' => trans('accounting::accounting.customer.type_occasional'),
    ];
    $currencyOptions = is_array($currencyOptions) ? $currencyOptions : [];
    $defaultCurrency = strtoupper((string) $defaultCurrency);
    $normalizedCurrencyOptions = [];
    foreach ($currencyOptions as $currencyCode => $currencyLabel) {
        $normalizedCurrencyOptions[strtoupper((string) $currencyCode)] = $currencyLabel;
    }
    $currencyOptions = $normalizedCurrencyOptions;
@endphp
<div class="sales-customer-picker" data-sales-customer-picker id="{{ $pickerId }}"
     data-search-url="{{ e($searchUrl) }}"
     data-create-url="{{ e($createUrl) }}"
     data-initial-id="{{ e($selectedId) }}"
     data-initial-customer-id="{{ e($selectedCustomerId) }}"
     data-initial-text="{{ e($selectedText !== '' ? $selectedText : ($selectedId !== '' ? ('#'.$selectedId) : '')) }}"
     data-customer-edit-base-url="{{ e(route('admin.accounting.customers.index')) }}"
     data-supplier-create-url="{{ e(route('admin.accounting.suppliers.create')) }}"
     data-default-currency="{{ e($defaultCurrency) }}"
     data-modal-id="{{ $modalId }}"
     data-placeholder="{{ e(trans('accounting::accounting.structured_resource_forms.select_placeholder')) }}"
     data-msg-saving="{{ e(trans('accounting::accounting.sales_customer_picker.messages.saving')) }}"
     data-msg-created="{{ e(trans('accounting::accounting.sales_customer_picker.messages.created')) }}"
     data-msg-error-generic="{{ e(trans('accounting::accounting.sales_customer_picker.messages.error_generic')) }}"
     data-msg-no-results="{{ e(trans('accounting::accounting.structured_resource_forms.select_placeholder')) }}">
    <label class="form-label" for="{{ $id }}">{{ $label ?? trans('accounting::accounting.invoice.customer_id') }} @if($required)<span class="text-danger">*</span>@endif</label>
    <div class="card border-primary border-opacity-50" data-sales-customer-card>
        <div class="card-body p-3">
            <div class="sales-customer-picker__selected d-none mb-2" data-selected-box>
                <div>
                    <div class="fw-semibold text-success" data-selected-text></div>
                    <small class="text-muted" data-selected-id></small>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary d-none" data-customer-edit-link>
                            <i class="ph-pencil-simple me-1"></i>{{ trans('accounting::accounting.sales_customer_picker.actions.edit_customer') }}
                        </a>
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-warning d-none" data-customer-to-supplier-link>
                            <i class="ph-arrows-left-right me-1"></i>{{ trans('accounting::accounting.sales_customer_picker.actions.convert_to_supplier') }}
                        </a>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-clear-selection>
                    <i class="ph-x"></i>
                </button>
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                <input type="text"
                       class="form-control"
                       data-search-input
                       placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}"
                       autocomplete="off">
                <input type="hidden"
                       name="{{ $name }}"
                       id="{{ $id }}"
                       value="{{ $selectedId }}"
                       @if($required) required @endif>
                <button type="button"
                        class="btn btn-outline-primary text-nowrap"
                        data-sales-customer-picker-open
                        data-bs-toggle="modal"
                        data-bs-target="#{{ $modalId }}">
                    <i class="ph-user-plus me-1"></i>{{ trans('accounting::accounting.sales_customer_picker.actions.new_customer') }}
                </button>
            </div>
            <div class="list-group mt-2 d-none sales-customer-picker__results" data-search-results></div>
        </div>
    </div>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('accounting::accounting.sales_customer_picker.modal.title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('accounting::accounting.common.back') }}"></button>
                </div>
                <div data-sales-customer-create-form>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none mb-3" data-sales-customer-picker-errors></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" data-sales-customer-field data-required="1" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.type') }} <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" data-sales-customer-field data-required="1" disabled>
                                    @foreach($typeOptions as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" @selected((string) $typeKey === 'regular')>{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.national_code') }}</label>
                                <input type="text" name="national_code" class="form-control" data-sales-customer-field disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.phone') }}</label>
                                <input type="text" name="phone" class="form-control" data-sales-customer-field disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.email') }}</label>
                                <input type="email" name="email" class="form-control" data-sales-customer-field disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.credit_limit') }}</label>
                                <input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="0" data-sales-customer-field disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.customer.default_currency') }}</label>
                                <select name="default_currency_code" class="form-select" data-sales-customer-field disabled>
                                    <option value="">{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}</option>
                                    @foreach($currencyOptions as $currencyCode => $currencyLabel)
                                        <option value="{{ $currencyCode }}" @selected((string) $currencyCode === $defaultCurrency)>{{ $currencyLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4 pt-2">
                                    <input type="hidden" name="active" value="0" data-sales-customer-field disabled>
                                    <input class="form-check-input" type="checkbox" name="active" value="1" checked data-sales-customer-field disabled>
                                    <label class="form-check-label">{{ trans('accounting::accounting.customer.is_active') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ trans('accounting::accounting.customer.address') }}</label>
                                <textarea name="address" class="form-control" rows="3" data-sales-customer-field disabled></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ trans('accounting::accounting.structured_resource_forms.cancel') }}</button>
                        <button type="button" class="btn btn-primary" data-sales-customer-picker-submit>
                            <i class="ph-floppy-disk-back me-1"></i>{{ trans('accounting::accounting.sales_customer_picker.actions.save_customer') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
