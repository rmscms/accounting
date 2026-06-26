{{-- فرم ساختاریافتهٔ ادمین accounting از روی getFieldsForm() — نمایهٔ بصری از $formProfile --}}
@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? config('app.name'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    $formProfile = $formProfile ?? 'catalog';
    $formSlug = $formSlug ?? '';
    $fieldRows = $fieldRows ?? [];
    $baseRoute = $baseRoute ?? 'accounting.dashboard';
    $indexRoute = $indexRoute ?? 'admin.accounting.dashboard';
    $routeParam = $routeParam ?? null;
    $defaultCurrency = $defaultCurrency ?? 'IRR';
    $amountDecimalPlaces = (int) ($amountDecimalPlaces ?? 0);
    $supplierInvoiceItemsFragmentUrl = $supplierInvoiceItemsFragmentUrl ?? null;
    $supplierInvoiceItemsStoreUrl = $supplierInvoiceItemsStoreUrl ?? null;
    $purchaseOrderItemsFragmentUrl = $purchaseOrderItemsFragmentUrl ?? null;
    $purchaseOrderItemsStoreUrl = $purchaseOrderItemsStoreUrl ?? null;
    $purchaseOrderWarehouseReceiptPdfUrl = $purchaseOrderWarehouseReceiptPdfUrl ?? null;
    $purchaseOrderFromPoInvoiceGate = $purchaseOrderFromPoInvoiceGate ?? ['can' => false, 'reason' => null, 'existing_invoice_id' => null];
    $supplierSelectInitialText = $supplierSelectInitialText ?? null;
    $supplierInvoiceSelectInitialText = $supplierInvoiceSelectInitialText ?? null;
    $debitNotePrefillSupplierId = $debitNotePrefillSupplierId ?? null;
    $debitNotePrefillInvoiceId = $debitNotePrefillInvoiceId ?? null;
    $supplierRefundPrefillSupplierId = $supplierRefundPrefillSupplierId ?? null;
    $supplierRefundPrefillInvoiceId = $supplierRefundPrefillInvoiceId ?? null;
    $supplierRefundPrefillPurchaseOrderId = $supplierRefundPrefillPurchaseOrderId ?? null;
    $supplierRefundContextInvoice = $supplierRefundContextInvoice ?? null;
    $supplierRefundContextPurchaseOrder = $supplierRefundContextPurchaseOrder ?? null;
    $supplierSearchUrlOverride = $supplierSearchUrlOverride ?? null;
    $defaultSupplierInvoiceNumber = $defaultSupplierInvoiceNumber ?? null;
    $supplierInvoicePrefillSupplierId = $supplierInvoicePrefillSupplierId ?? null;
    $supplierInvoicePrefillPurchaseOrderId = $supplierInvoicePrefillPurchaseOrderId ?? null;
    $supplierInvoicePrefillTotalFromPo = $supplierInvoicePrefillTotalFromPo ?? null;
    $defaultPurchaseOrderNumber = $defaultPurchaseOrderNumber ?? null;
    $poNumberUniquenessUrl = $poNumberUniquenessUrl ?? null;
    $invoiceNumberUniquenessUrl = $invoiceNumberUniquenessUrl ?? null;
    $partySearchUrl = $partySearchUrl ?? null;
    $defaultSupplierCode = $defaultSupplierCode ?? null;
    $partySelectInitialText = $partySelectInitialText ?? null;
    $linkedCustomerPrefillId = $linkedCustomerPrefillId ?? null;
    $linkedCustomerSelectInitialText = $linkedCustomerSelectInitialText ?? null;
    $linkedCustomerPrefillValues = is_array($linkedCustomerPrefillValues ?? null) ? $linkedCustomerPrefillValues : [];
    $customerCanConvertToSupplier = (bool) ($customerCanConvertToSupplier ?? false);
    $customerConvertToSupplierUrl = $customerConvertToSupplierUrl ?? null;
    $supplierPaymentPrefillSupplierId = $supplierPaymentPrefillSupplierId ?? null;
    $supplierPaymentPrefillInvoiceId = $supplierPaymentPrefillInvoiceId ?? null;
    $supplierPaymentPrefillPurchaseOrderId = $supplierPaymentPrefillPurchaseOrderId ?? null;
    $supplierPaymentPoLockedFromQuery = (bool) ($supplierPaymentPoLockedFromQuery ?? false);
    $supplierPaymentLinkedPurchaseOrderLabel = $supplierPaymentLinkedPurchaseOrderLabel ?? null;
    $supplierPaymentLinkedPurchaseOrderEditUrl = $supplierPaymentLinkedPurchaseOrderEditUrl ?? null;
    $supplierPaymentPrefillAmount = $supplierPaymentPrefillAmount ?? null;
    $defaultSupplierPaymentNumber = $defaultSupplierPaymentNumber ?? null;
    $supplierPaymentNumberUniquenessUrl = $supplierPaymentNumberUniquenessUrl ?? null;
    $customerPaymentSearchUrl = $customerPaymentSearchUrl ?? route('admin.accounting.customer-invoices.search-customers');
    $customerPaymentPrefillId = $customerPaymentPrefillId ?? null;
    $customerPaymentSelectInitialText = $customerPaymentSelectInitialText ?? null;
    $customerPaymentPrefillInvoiceId = $customerPaymentPrefillInvoiceId ?? null;
    $customerPaymentPrefillAmount = $customerPaymentPrefillAmount ?? null;
    $defaultCustomerPaymentNumber = $defaultCustomerPaymentNumber ?? null;
    $customerPaymentNumberUniquenessUrl = $customerPaymentNumberUniquenessUrl ?? null;
    $customerInvoiceSearchUrl = $customerInvoiceSearchUrl ?? route('admin.accounting.customer-invoices.search-customers');
    $customerQuickCreateUrl = $customerQuickCreateUrl ?? null;
    $customerPrefillId = $customerPrefillId ?? null;
    $customerSelectInitialText = $customerSelectInitialText ?? null;
    $customerQuickCreateTypeOptions = is_array($customerQuickCreateTypeOptions ?? null) ? $customerQuickCreateTypeOptions : [];
    $customerQuickCreateCurrencyOptions = is_array($customerQuickCreateCurrencyOptions ?? null) ? $customerQuickCreateCurrencyOptions : [];
    $creditNoteReferenceInvoicePreviewUrlTemplate = $creditNoteReferenceInvoicePreviewUrlTemplate ?? '';
    $creditNoteContextInvoice = $creditNoteContextInvoice ?? null;
    $defaultCustomerInvoiceNumber = $defaultCustomerInvoiceNumber ?? null;
    $fxCardEnabled = (bool) ($fxCardEnabled ?? false);
    $fxCardCurrencyField = (string) ($fxCardCurrencyField ?? 'currency_code');
    $fxCardRateField = (string) ($fxCardRateField ?? '');
    $fxCardBaseAmountField = (string) ($fxCardBaseAmountField ?? '');
    $fxCardTotalField = (string) ($fxCardTotalField ?? 'total_amount');
    $fxCardBaseCurrency = strtoupper((string) ($fxCardBaseCurrency ?? $defaultCurrency ?? 'IRT'));
    $fxCardCurrencyOptions = is_array($fxCardCurrencyOptions ?? null) ? $fxCardCurrencyOptions : [];
    $fxCardCurrencyMeta = is_array($fxCardCurrencyMeta ?? null) ? $fxCardCurrencyMeta : [];
    $fxCardInitialCurrency = strtoupper((string) ($fxCardInitialCurrency ?? $fxCardBaseCurrency));
    $fxCardInitialRate = (string) ($fxCardInitialRate ?? '1');
    $fxCardInitialBaseAmount = (string) ($fxCardInitialBaseAmount ?? '');
    $fxCardBaseDecimals = max(0, min(6, (int) (($fxCardCurrencyMeta[$fxCardBaseCurrency]['decimals'] ?? 4))));
    $fxCardCollapseId = 'accounting-fx-card-' . ($formSlug ?: 'resource');
    $supplierInvoicePostedLocked = ($formSlug ?? '') === 'supplier_invoices'
        && $isEdit
        && isset($model)
        && $model
        && ((int) ($model->document_id ?? 0) > 0);
    $profileCardClass = match ($formProfile) {
        'entity' => 'border-start border-4 border-info border-opacity-50',
        'document' => 'border-start border-4 border-warning border-opacity-50',
        'treasury' => 'border-start border-4 border-success border-opacity-50',
        default => 'border-primary border-opacity-25',
    };
@endphp
<div class="container-fluid accounting-structured-form" data-form-profile="{{ $formProfile }}" data-form-slug="{{ $formSlug }}"
    @if(($formSlug ?? '') === 'cheques')
        data-cheque-setup-missing="{{ !empty($chequeClearingSetupMissing) ? '1' : '0' }}"
        data-cheque-setup-modal-id="cheque-setup-missing-modal"
    @endif
    @if(($formSlug ?? '') === 'supplier_invoices')
        data-invoice-msg-taken="{{ e(trans('accounting::accounting.supplier_invoice.invoice_number_taken')) }}"
    @elseif(($formSlug ?? '') === 'customer_invoices')
        data-invoice-msg-taken="{{ e(trans('accounting::accounting.invoice.invoice_number_taken')) }}"
    @elseif(($formSlug ?? '') === 'supplier_payments')
        data-payment-number-msg-taken="{{ e(trans('accounting::accounting.payment.payment_number_taken')) }}"
        data-payment-number-msg-available="{{ e(trans('accounting::accounting.payment.payment_number_available')) }}"
    @elseif(($formSlug ?? '') === 'customer_payments')
        data-payment-number-msg-taken="{{ e(trans('accounting::accounting.payment.payment_number_taken')) }}"
        data-payment-number-msg-available="{{ e(trans('accounting::accounting.payment.payment_number_available')) }}"
    @elseif(($formSlug ?? '') === 'purchase_orders')
        data-po-msg-taken="{{ e(trans('accounting::accounting.purchase_order.po_number_taken')) }}"
    @endif>
    <div class="card border-0 shadow-sm {{ $profileCardClass }}">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $htmlPageTitle ?? config('app.name') }}</h5>
                <small class="text-muted d-block">
                    {{ trans('accounting::accounting.structured_resource_forms.profiles.'.$formProfile.'.subtitle') }}
                </small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if(($formSlug ?? '') === 'customers' && ! empty($isEdit) && ! empty($customerCanConvertToSupplier) && ! empty($customerConvertToSupplierUrl))
                    <a href="{{ $customerConvertToSupplierUrl }}" class="btn btn-outline-primary btn-sm">
                        <i class="ph-arrows-left-right me-1"></i>تبدیل به تامین‌کننده
                    </a>
                @endif
                <a href="{{ route($indexRoute) }}" class="btn btn-light btn-sm">
                    <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_resource_forms.back_to_list') }}
                </a>
            </div>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            @php
                                $renderedError = e((string) $error);
                                $settingsMarker = '__ACCOUNTING_SETTINGS_URL__';
                                if (str_contains((string) $error, $settingsMarker)) {
                                    $settingsLink = '<a href="' . e(route('admin.accounting.settings.index')) . '" class="fw-semibold">تنظیمات حسابداری</a>';
                                    $renderedError = str_replace($settingsMarker, $settingsLink, $renderedError);
                                }
                            @endphp
                            <li>{!! $renderedError !!}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(($formSlug ?? '') === 'manual_journals')
                <div class="alert alert-info border border-info border-opacity-50 mb-3" role="status">
                    <h6 class="alert-heading fw-semibold mb-2">{{ trans('accounting::accounting.manual_journal_form.notice_title') }}</h6>
                    <p class="small mb-2">{{ trans('accounting::accounting.manual_journal_form.notice_body') }}</p>
                    <ul class="small mb-0 ps-3">
                        <li class="mb-1">
                            <a href="{{ route('admin.accounting.manual-journals.index') }}">{{ trans('accounting::accounting.manual_journal_form.link_list') }}</a>
                            <code class="ms-1 user-select-all">{{ parse_url(route('admin.accounting.manual-journals.index'), PHP_URL_PATH) }}</code>
                        </li>
                        <li class="mb-1">
                            <a href="{{ route('admin.accounting.guides.opening-balance') }}">{{ trans('accounting::accounting.manual_journal_form.link_guide') }}</a>
                        </li>
                        <li>
                            <a href="{{ route('admin.accounting.reports.trial-balance') }}">{{ trans('accounting::accounting.manual_journal_form.link_trial') }}</a>
                            <code class="ms-1 user-select-all">{{ parse_url(route('admin.accounting.reports.trial-balance'), PHP_URL_PATH) }}</code>
                        </li>
                    </ul>
                </div>
            @endif

            @php
                $pageDescriptionLines = trans('accounting::accounting.structured_resource_forms.profiles.'.$formProfile.'.page_description_paragraphs');
                if (! is_array($pageDescriptionLines)) {
                    $pageDescriptionLines = is_string($pageDescriptionLines) && trim($pageDescriptionLines) !== ''
                        ? [$pageDescriptionLines]
                        : [];
                }
            @endphp
            <x-accounting::page-description class="mt-0 mb-3" :title="trans('accounting::accounting.structured_resource_forms.profiles.'.$formProfile.'.page_description_title')">
                @foreach($pageDescriptionLines as $line)
                    <p class="mb-2">{{ $line }}</p>
                @endforeach
            </x-accounting::page-description>

            @if(($formSlug ?? '') === 'purchase_orders')
                @include('accounting::admin.form._workflow_purchase_order', ['isEdit' => $isEdit, 'model' => $model ?? null])
            @endif
            @if(($formSlug ?? '') === 'supplier_invoices')
                @include('accounting::admin.form._workflow_supplier_invoice', ['isEdit' => $isEdit, 'model' => $model ?? null])
            @endif
            @if(($formSlug ?? '') === 'supplier_payments' && empty($isEdit))
                @include('accounting::admin.form._workflow_supplier_payment')
            @endif
            @if(($formSlug ?? '') === 'customer_invoices')
                @include('accounting::admin.form._workflow_customer_invoice', ['isEdit' => $isEdit, 'model' => $model ?? null])
            @endif
            @if(($formSlug ?? '') === 'debit_notes')
                @include('accounting::admin.form._workflow_debit_note', ['isEdit' => $isEdit, 'model' => $model ?? null])
            @endif
            @if(($formSlug ?? '') === 'credit_notes')
                @include('accounting::admin.form._workflow_credit_note', ['isEdit' => $isEdit, 'model' => $model ?? null])
            @endif
            @if(($formSlug ?? '') === 'supplier_refunds' && ! empty($supplierRefundContextInvoice))
                @include('accounting::admin.form._workflow_supplier_refund', ['invoice' => $supplierRefundContextInvoice])
            @elseif(($formSlug ?? '') === 'supplier_refunds' && ! empty($supplierRefundContextPurchaseOrder))
                @include('accounting::admin.form._workflow_supplier_refund_po', ['purchaseOrder' => $supplierRefundContextPurchaseOrder])
            @endif

            @if($isEdit && $model && $routeParam)
                <form method="post" action="{{ route('admin.' . $baseRoute . '.update', [$routeParam => $model->getKey()]) }}" class="row g-3">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.' . $baseRoute . '.store') }}" class="row g-3">
                    @csrf
            @endif

            @if($supplierInvoicePostedLocked)
                <div class="col-12">
                    <div class="alert alert-warning border mb-2">
                        {{ trans('accounting::accounting.supplier_invoice.header_locked_document') }}
                    </div>
                </div>
                <fieldset disabled>
            @endif

            @if(($formSlug ?? '') === 'cheques' && !empty($chequeClearingSetupMissing))
                <div class="col-12">
                    <div class="alert alert-warning border mb-2">
                        <div class="fw-semibold mb-1">{{ trans('accounting::accounting.cheques.setup_warning_title') }}</div>
                        <div class="small mb-2">{{ trans('accounting::accounting.cheques.setup_warning_body') }}</div>
                        @if(!empty($chequeSetupIssues) && is_array($chequeSetupIssues))
                            <ul class="small mb-2 ps-3">
                                @foreach($chequeSetupIssues as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $chequeSettingsUrl ?? route('admin.accounting.settings.index') }}" class="btn btn-sm btn-outline-primary">
                                {{ trans('accounting::accounting.cheques.setup_warning_settings_btn') }}
                            </a>
                            <a href="{{ $chequeAccountsUrl ?? route('admin.accounting.accounts.index') }}" class="btn btn-sm btn-outline-secondary">
                                {{ trans('accounting::accounting.cheques.setup_warning_accounts_btn') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endif
            @if(($formSlug ?? '') === 'cheques' && !empty($chequeClearingSetupMissing))
                <div class="modal fade" id="cheque-setup-missing-modal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ trans('accounting::accounting.cheques.setup_confirm_modal_title') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">{{ trans('accounting::accounting.cheques.setup_confirm_modal_body') }}</p>
                                @if(!empty($chequeSetupIssues) && is_array($chequeSetupIssues))
                                    <ul class="mt-3 mb-0 ps-3">
                                        @foreach($chequeSetupIssues as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                    {{ trans('accounting::accounting.cheques.setup_confirm_modal_dismiss_btn') }}
                                </button>
                                <a href="{{ $chequeAccountsUrl ?? route('admin.accounting.accounts.index') }}" class="btn btn-outline-secondary">
                                    {{ trans('accounting::accounting.cheques.setup_confirm_modal_accounts_btn') }}
                                </a>
                                <a href="{{ $chequeSettingsUrl ?? route('admin.accounting.settings.index') }}" class="btn btn-primary">
                                    {{ trans('accounting::accounting.cheques.setup_confirm_modal_settings_btn') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            @if(($formSlug ?? '') === 'cheques' && (int)($chequeActiveChequebooksCount ?? 0) <= 0)
                <div class="col-12">
                    <div class="alert alert-info border mb-2">
                        {{ trans('accounting::accounting.cheques.chequebook_missing_warning') }}
                    </div>
                </div>
            @endif

            @if($fxCardEnabled && in_array(($formSlug ?? ''), ['supplier_invoices', 'purchase_orders', 'supplier_payments'], true) && $fxCardRateField !== '' && $fxCardBaseAmountField !== '')
                <div class="col-12">
                    <div class="card border border-info border-opacity-25 mb-1">
                        <div class="card-header py-2">
                            <button class="btn btn-link text-decoration-none p-0 collapsed d-flex align-items-center w-100 text-start"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $fxCardCollapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $fxCardCollapseId }}">
                                <i class="ph-caret-down me-2 accounting-collapse-help__caret"></i>
                                <span class="fw-semibold">{{ trans('accounting::accounting.common.currency') }} / FX</span>
                            </button>
                        </div>
                        <div id="{{ $fxCardCollapseId }}" class="collapse">
                            <div class="card-body">
                                <div class="row g-3 js-fx-card"
                                     data-total-field="{{ $fxCardTotalField }}"
                                     data-currency-field="{{ $fxCardCurrencyField }}"
                                     data-rate-field="{{ $fxCardRateField }}"
                                     data-base-amount-field="{{ $fxCardBaseAmountField }}"
                                     data-base-currency="{{ $fxCardBaseCurrency }}"
                                     data-currency-meta='@json($fxCardCurrencyMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)'>
                                    <div class="col-lg-4 col-md-6 order-3 order-lg-1">
                                        <label class="form-label">{{ trans('accounting::accounting.common.amount') }} ({{ $fxCardBaseCurrency }})</label>
                                        <input type="text"
                                               class="form-control amount-decimal @error($fxCardBaseAmountField) is-invalid @enderror"
                                               name="{{ $fxCardBaseAmountField }}"
                                               id="fld-{{ $fxCardBaseAmountField }}"
                                               data-type="amount-decimal"
                                               data-decimals="{{ $fxCardBaseDecimals }}"
                                               inputmode="decimal"
                                               value="{{ $fxCardInitialBaseAmount }}"
                                               readonly>
                                        @error($fxCardBaseAmountField)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-4 col-md-6 order-1 order-lg-2">
                                        <label class="form-label">{{ trans('accounting::accounting.currency.exchange_rate') }} <span class="text-danger">*</span></label>
                                        <input type="text"
                                               class="form-control amount-decimal @error($fxCardRateField) is-invalid @enderror"
                                               name="{{ $fxCardRateField }}"
                                               id="fld-{{ $fxCardRateField }}"
                                               data-type="amount-decimal"
                                               data-decimals="6"
                                               inputmode="decimal"
                                               value="{{ $fxCardInitialRate }}"
                                               required>
                                        @error($fxCardRateField)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-4 col-md-12 order-2 order-lg-3">
                                        <label class="form-label">{{ trans('accounting::accounting.common.currency') }} <span class="text-danger">*</span></label>
                                        <select class="form-select @error($fxCardCurrencyField) is-invalid @enderror"
                                                name="{{ $fxCardCurrencyField }}"
                                                id="fld-{{ $fxCardCurrencyField }}"
                                                required>
                                            @foreach($fxCardCurrencyOptions as $currencyCode => $currencyLabel)
                                                <option value="{{ $currencyCode }}" @selected((string) $fxCardInitialCurrency === (string) $currencyCode)>{{ $currencyLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error($fxCardCurrencyField)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(($formSlug ?? '') === 'supplier_refunds' && empty($isEdit) && ! empty($supplierRefundPrefillPurchaseOrderId))
                <input type="hidden" name="refund_context_purchase_order_id" value="{{ e((string) $supplierRefundPrefillPurchaseOrderId) }}">
            @endif

            @php
                $effectiveSupplierIdForDeps = old('supplier_id', ($isEdit && isset($model) && $model && isset($model->supplier_id)) ? $model->supplier_id : null);
                if (($effectiveSupplierIdForDeps === null || $effectiveSupplierIdForDeps === '') && ! empty($debitNotePrefillSupplierId)) {
                    $effectiveSupplierIdForDeps = $debitNotePrefillSupplierId;
                }
                if (($effectiveSupplierIdForDeps === null || $effectiveSupplierIdForDeps === '') && ! empty($supplierRefundPrefillSupplierId)) {
                    $effectiveSupplierIdForDeps = $supplierRefundPrefillSupplierId;
                }
                if (($effectiveSupplierIdForDeps === null || $effectiveSupplierIdForDeps === '') && ! empty($supplierInvoicePrefillSupplierId)) {
                    $effectiveSupplierIdForDeps = $supplierInvoicePrefillSupplierId;
                }
            @endphp

            @foreach($fieldRows as $row)
                @if(($formSlug ?? '') === 'suppliers' && ($row['key'] ?? '') === 'linked_customer_id' && !empty($isEdit))
                    @continue
                @endif
                @php
                    $key = $row['key'];
                    $skipBecauseFxCardHandled = $fxCardEnabled
                        && in_array(($formSlug ?? ''), ['supplier_invoices', 'purchase_orders', 'supplier_payments'], true)
                        && in_array($key, [$fxCardCurrencyField, $fxCardRateField, $fxCardBaseAmountField], true);
                @endphp
                @if($skipBecauseFxCardHandled)
                    @continue
                @endif
                @php
                    $label = $row['label'];
                    $required = !empty($row['required']);
                    // Disable browser-native required validation for customers.credit_limit.
                    // We keep server-side validation and normalization in controller.
                    if (($formSlug ?? '') === 'customers' && ($key ?? '') === 'credit_limit') {
                        $required = false;
                    }
                    $widget = $row['widget'] ?? 'text';
                    $oldVal = old($key);
                    $modelVal = ($isEdit && $model && isset($model->{$key})) ? $model->{$key} : null;
                    $val = $oldVal !== null && $oldVal !== '' ? $oldVal : $modelVal;
                    if (($formSlug ?? '') === 'suppliers' && $key === 'code' && ($val === null || $val === '') && !empty($defaultSupplierCode)) {
                        $val = $defaultSupplierCode;
                    }
                    if (($formSlug ?? '') === 'suppliers' && ! $isEdit && $key === 'linked_customer_id' && ($val === null || $val === '') && !empty($linkedCustomerPrefillId)) {
                        $val = $linkedCustomerPrefillId;
                    }
                    if (($formSlug ?? '') === 'suppliers' && ! $isEdit && ($val === null || $val === '') && array_key_exists($key, $linkedCustomerPrefillValues)) {
                        $prefillVal = $linkedCustomerPrefillValues[$key];
                        if ($prefillVal !== null && $prefillVal !== '') {
                            $val = $prefillVal;
                        }
                    }
                    if (($formSlug ?? '') === 'supplier_invoices' && $key === 'invoice_number' && ($val === null || $val === '') && !empty($defaultSupplierInvoiceNumber)) {
                        $val = $defaultSupplierInvoiceNumber;
                    }
                    if (($formSlug ?? '') === 'supplier_invoices' && ! $isEdit) {
                        if ($key === 'supplier_id' && ($val === null || $val === '') && ! empty($supplierInvoicePrefillSupplierId)) {
                            $val = $supplierInvoicePrefillSupplierId;
                        }
                        if ($key === 'purchase_order_id' && ($val === null || $val === '') && ! empty($supplierInvoicePrefillPurchaseOrderId)) {
                            $val = $supplierInvoicePrefillPurchaseOrderId;
                        }
                        if ($key === 'total_amount' && ($val === null || $val === '') && ! empty($supplierInvoicePrefillTotalFromPo)) {
                            $val = $supplierInvoicePrefillTotalFromPo;
                        }
                    }
                    if (($formSlug ?? '') === 'purchase_orders' && $key === 'po_number' && ($val === null || $val === '') && !empty($defaultPurchaseOrderNumber)) {
                        $val = $defaultPurchaseOrderNumber;
                    }
                    if (($formSlug ?? '') === 'customer_invoices' && $key === 'invoice_number' && ($val === null || $val === '') && !empty($defaultCustomerInvoiceNumber)) {
                        $val = $defaultCustomerInvoiceNumber;
                    }
                    if (($formSlug ?? '') === 'customer_invoices' && $key === 'customer_id' && ($val === null || $val === '') && !empty($customerPrefillId)) {
                        $val = $customerPrefillId;
                    }
                    if ((($formSlug ?? '') === 'supplier_payments' || ($formSlug ?? '') === 'supplier_advances') && ! $isEdit) {
                        if ($key === 'supplier_id' && ($val === null || $val === '') && $supplierPaymentPrefillSupplierId !== null && $supplierPaymentPrefillSupplierId !== '') {
                            $val = $supplierPaymentPrefillSupplierId;
                        }
                        if (($formSlug ?? '') === 'supplier_payments') {
                            if ($key === 'supplier_invoice_id' && ($val === null || $val === '') && $supplierPaymentPrefillInvoiceId !== null && $supplierPaymentPrefillInvoiceId !== '') {
                                $val = $supplierPaymentPrefillInvoiceId;
                            }
                            if ($key === 'purchase_order_id' && ($val === null || $val === '') && $supplierPaymentPrefillPurchaseOrderId !== null && $supplierPaymentPrefillPurchaseOrderId !== '') {
                                $val = $supplierPaymentPrefillPurchaseOrderId;
                            }
                            if ($key === 'payment_number' && ($val === null || $val === '') && ! empty($defaultSupplierPaymentNumber)) {
                                $val = $defaultSupplierPaymentNumber;
                            }
                            if ($key === 'payment_date' && ($val === null || $val === '')) {
                                $val = now()->toDateString();
                            }
                            if ($key === 'amount' && ($val === null || $val === '') && $supplierPaymentPrefillAmount !== null && $supplierPaymentPrefillAmount !== '') {
                                $val = $supplierPaymentPrefillAmount;
                            }
                        }
                        if (($formSlug ?? '') === 'supplier_advances' && $key === 'advance_date' && ($val === null || $val === '')) {
                            $val = now()->toDateString();
                        }
                    }
                    if (($formSlug ?? '') === 'customer_payments' && ! $isEdit) {
                        if ($key === 'customer_id' && ($val === null || $val === '') && $customerPaymentPrefillId !== null && $customerPaymentPrefillId !== '') {
                            $val = $customerPaymentPrefillId;
                        }
                        if ($key === 'customer_invoice_id' && ($val === null || $val === '') && $customerPaymentPrefillInvoiceId !== null && $customerPaymentPrefillInvoiceId !== '') {
                            $val = $customerPaymentPrefillInvoiceId;
                        }
                        if ($key === 'payment_number' && ($val === null || $val === '') && ! empty($defaultCustomerPaymentNumber)) {
                            $val = $defaultCustomerPaymentNumber;
                        }
                        if ($key === 'payment_date' && ($val === null || $val === '')) {
                            $val = now()->toDateString();
                        }
                        if ($key === 'amount' && ($val === null || $val === '') && $customerPaymentPrefillAmount !== null && $customerPaymentPrefillAmount !== '') {
                            $val = $customerPaymentPrefillAmount;
                        }
                    }
                    if (($formSlug ?? '') === 'debit_notes' && ! $isEdit) {
                        if ($key === 'supplier_id' && ($val === null || $val === '') && $debitNotePrefillSupplierId !== null && $debitNotePrefillSupplierId !== '') {
                            $val = $debitNotePrefillSupplierId;
                        }
                        if ($key === 'supplier_invoice_id' && ($val === null || $val === '') && $debitNotePrefillInvoiceId !== null && $debitNotePrefillInvoiceId !== '') {
                            $val = $debitNotePrefillInvoiceId;
                        }
                    }
                    if (($formSlug ?? '') === 'supplier_refunds') {
                        if ($key === 'supplier_id' && ($val === null || $val === '') && $supplierRefundPrefillSupplierId !== null && $supplierRefundPrefillSupplierId !== '') {
                            $val = $supplierRefundPrefillSupplierId;
                        }
                        if ($key === 'supplier_invoice_id' && ($val === null || $val === '') && $supplierRefundPrefillInvoiceId !== null && $supplierRefundPrefillInvoiceId !== '') {
                            $val = $supplierRefundPrefillInvoiceId;
                        }
                    }
                    if (($val === null || $val === '') && array_key_exists('default_value', $row) && $row['default_value'] !== null && $row['default_value'] !== '') {
                        $val = $row['default_value'];
                    }
                @endphp
                @php
                    $wideWidgets = ['textarea', 'boolean', 'ajax_supplier_select', 'ajax_supplier_invoice_select', 'ajax_party_optional_select', 'ajax_customer_optional_select', 'ajax_customer_select', 'customer_payment_customer_picker', 'payment_destination_picker', 'supplier_payment_purchase_order'];
                    $colClass = match ($formProfile) {
                        'entity' => in_array($widget, $wideWidgets, true) ? 'col-12' : 'col-md-6',
                        default => in_array($widget, $wideWidgets, true) ? 'col-12' : 'col-md-6',
                    };
                    $settlementDestWrap = ! empty($row['wrap_settlement_destination']);
                    $settlementDestExtra = $settlementDestWrap ? ' supplier-invoice-settlement-destination js-settlement-paid-at-source-only d-none' : '';
                    $customerInvoiceMixedOnly = (($formSlug ?? '') === 'customer_invoices' && $key === 'upfront_payment_amount');
                    $customerInvoiceMixedOnlyExtra = $customerInvoiceMixedOnly ? ' js-settlement-mixed-only d-none' : '';
                @endphp
                <div class="{{ $widget === 'hidden' ? 'd-none' : $colClass }}{{ $settlementDestExtra }}{{ $customerInvoiceMixedOnlyExtra }} @if(($formSlug ?? '') === 'cashboxes' && $key === 'account_id') js-treasury-manual-account-wrap @endif">
                    @if($widget === 'hidden')
                        @php
                            if (($val === null || $val === '') && array_key_exists('default_value', $row) && $row['default_value'] !== null && $row['default_value'] !== '') {
                                $val = $row['default_value'];
                            }
                        @endphp
                        <input type="hidden" name="{{ $key }}" id="fld-{{ $key }}" value="{{ is_scalar($val) || $val === null ? (string) ($val ?? '') : '' }}">
                    @elseif($widget === 'textarea')
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <textarea name="{{ $key }}" id="fld-{{ $key }}" rows="{{ (int) ($row['rows'] ?? 3) }}" class="form-control @error($key) is-invalid @enderror">{{ is_scalar($val) || $val === null ? (string) ($val ?? '') : '' }}</textarea>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'boolean')
                        @php
                            $ov = old($key);
                            if ($ov === null) {
                                if ($isEdit && $model && isset($model->{$key})) {
                                    $boolOn = (bool) $model->{$key};
                                } elseif (array_key_exists('default_value', $row)) {
                                    $boolOn = (bool) $row['default_value'];
                                } else {
                                    $boolOn = false;
                                }
                            } else {
                                $boolOn = $ov === '1' || $ov === 1 || $ov === true;
                            }
                        @endphp
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input class="form-check-input @if(($formSlug ?? '') === 'cashboxes' && $key === 'auto_create_account') js-treasury-auto-create-toggle @endif"
                                   type="checkbox"
                                   name="{{ $key }}"
                                   id="fld-{{ $key }}"
                                   value="1"
                                   @checked($boolOn)>
                            <label class="form-check-label" for="fld-{{ $key }}">{{ $label }}</label>
                        </div>
                        @if(($formSlug ?? '') === 'cashboxes' && $key === 'auto_create_account')
                            <div class="form-text">{{ trans('accounting::accounting.treasury_sub_accounts.cashbox_hint') }}</div>
                        @endif
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'select')
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <select name="{{ $key }}"
                                id="fld-{{ $key }}"
                                class="form-select @if(!empty($row['use_enhanced'])) enhanced-select @endif @error($key) is-invalid @enderror"
                                @if($required) required @endif>
                            @if(empty($row['required']))
                                <option value="">{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}</option>
                            @endif
                            @foreach(($row['options'] ?? []) as $opt)
                                <option value="{{ $opt['value'] }}" @selected((string) $val === (string) $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @if(($formSlug ?? '') === 'cashboxes' && $key === 'account_id')
                            <div class="form-text js-treasury-manual-account-hint">{{ trans('accounting::accounting.treasury_sub_accounts.manual_account_hint') }}</div>
                        @endif
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'date')
                        @php
                            $pdVal = '';
                            if ($val) {
                                if ($val instanceof \DateTimeInterface) {
                                    if (function_exists('persian_date')) {
                                        $pdVal = \RMS\Helper\persian_date(\Illuminate\Support\Carbon::instance($val), 'Y/m/d');
                                    } else {
                                        $pdVal = (string) \Illuminate\Support\Carbon::instance($val)->format('Y-m-d');
                                    }
                                } elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val) && function_exists('persian_date')) {
                                    $pdVal = \RMS\Helper\persian_date(\Illuminate\Support\Carbon::parse($val), 'Y/m/d');
                                } else {
                                    $pdVal = (string) $val;
                                }
                            }
                        @endphp
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="{{ $key }}" id="fld-{{ $key }}" class="form-control persian-datepicker @error($key) is-invalid @enderror"
                               data-format="YYYY/MM/DD" value="{{ old($key, $pdVal) }}" autocomplete="off" @if($required) required @endif>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'amount')
                        @php
                            $isFxTotalAmountField = $fxCardEnabled
                                && in_array(($formSlug ?? ''), ['supplier_invoices', 'purchase_orders', 'supplier_payments'], true)
                                && $key === $fxCardTotalField;
                            $amountHintCurrency = $isFxTotalAmountField ? $fxCardInitialCurrency : $defaultCurrency;
                            $numVal = '';
                            if ($val !== null && $val !== '') {
                                $rawAmt = str_replace(',', '', (string) $val);
                                if (is_numeric($rawAmt)) {
                                    $numVal = number_format((float) $rawAmt, $amountDecimalPlaces, '.', ',');
                                } else {
                                    $numVal = (string) $val;
                                }
                            }
                        @endphp
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="{{ $key }}" id="fld-{{ $key }}" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                               @if($isFxTotalAmountField) data-fx-total-amount="1" @endif
                               class="form-control amount-decimal @error($key) is-invalid @enderror" value="{{ $numVal }}" autocomplete="off" @if($required) required @endif>
                        @if(($formSlug ?? '') === 'debit_notes' && $key === 'total_amount')
                            <div class="form-text">{{ trans('accounting::accounting.debit_note_form.total_amount_currency_hint') }}</div>
                        @else
                            <div class="form-text @if($isFxTotalAmountField) js-amount-currency-hint @endif"
                                 @if($isFxTotalAmountField) data-hint-template="{{ trans('accounting::accounting.structured_resource_forms.amount_hint', ['currency' => '__CURRENCY__']) }}" @endif>
                                {{ trans('accounting::accounting.structured_resource_forms.amount_hint', ['currency' => $amountHintCurrency]) }}
                            </div>
                        @endif
                        @if(($formSlug ?? '') === 'debit_notes' && $key === 'total_amount')
                            <div class="form-text">{{ trans('accounting::accounting.debit_note_form.total_amount_help') }}</div>
                        @endif
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'integer')
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <input type="number" name="{{ $key }}" id="fld-{{ $key }}" class="form-control @error($key) is-invalid @enderror" step="1"
                               value="{{ ($val !== null && $val !== '') ? (string) $val : '' }}" @if($required) required @endif>
                        @if(($formSlug ?? '') === 'supplier_payments' && $key === 'supplier_invoice_id' && ! $isEdit)
                            <div class="form-text">{{ trans('accounting::accounting.payment.supplier_invoice_optional_help') }}</div>
                        @endif
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'supplier_payment_purchase_order')
                        @if(($formSlug ?? '') !== 'supplier_payments')
                            <label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                            <input type="number" name="{{ $key }}" id="fld-{{ $key }}" class="form-control @error($key) is-invalid @enderror" step="1"
                                   value="{{ ($val !== null && $val !== '') ? (string) $val : '' }}" @if($required) required @endif>
                            @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @elseif($supplierPaymentPoLockedFromQuery && $supplierPaymentPrefillPurchaseOrderId !== null && $supplierPaymentPrefillPurchaseOrderId !== '')
                            <div class="border rounded bg-light px-3 py-2 mb-0">
                                <div class="fw-semibold small mb-1">{{ trans('accounting::accounting.payment.purchase_order_locked_title') }}</div>
                                <p class="small text-muted mb-2">
                                    {{ trans('accounting::accounting.payment.purchase_order_locked_body', ['label' => $supplierPaymentLinkedPurchaseOrderLabel ?? ('#'.$supplierPaymentPrefillPurchaseOrderId)]) }}
                                </p>
                                @if(!empty($supplierPaymentLinkedPurchaseOrderEditUrl))
                                    <a href="{{ $supplierPaymentLinkedPurchaseOrderEditUrl }}" class="btn btn-sm btn-outline-primary">{{ trans('accounting::accounting.payment.purchase_order_open') }}</a>
                                @endif
                            </div>
                            <input type="hidden" name="purchase_order_id" id="fld-purchase_order_id" value="{{ e((string) $supplierPaymentPrefillPurchaseOrderId) }}">
                            <input type="hidden" name="_supplier_payment_po_from_query" value="1">
                        @else
                            <label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                            <input type="number" name="{{ $key }}" id="fld-{{ $key }}" class="form-control @error($key) is-invalid @enderror" step="1" min="1"
                                   value="{{ ($val !== null && $val !== '') ? (string) $val : '' }}" @if($required) required @endif>
                            <div class="form-text">{{ trans('accounting::accounting.payment.purchase_order_optional_help') }}</div>
                            @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endif
                    @elseif($widget === 'payment_destination_picker')
                        @php
                            $pdpCtx = (string) ($row['payment_destination_context'] ?? 'supplier_payment');
                            $pdpUrl = trim((string) ($row['payment_destination_catalog_url'] ?? ''));
                            if ($pdpUrl === '') {
                                $pdpUrl = route('admin.accounting.ajax.payment-destinations', ['context' => $pdpCtx]);
                            }
                            $pdpI18n = trans('accounting::accounting.payment_destination');
                            $pdpI18n = is_array($pdpI18n) ? $pdpI18n : [];
                            $pdpSetup = [
                                'banks' => route('admin.accounting.banks.index'),
                                'cashboxes' => route('admin.accounting.cashboxes.index'),
                                'cheques' => route('admin.accounting.cheques.index'),
                                'pos_terminals' => route('admin.accounting.pos-terminals.index'),
                                'wallets' => route('admin.accounting.wallets.index'),
                                'payment_methods' => route('admin.accounting.payment-methods.index'),
                            ];
                            $pdpPrefix = (string) ($row['pdp_name_prefix'] ?? '');
                            $pdpBankKey = $pdpPrefix !== '' ? $pdpPrefix.'bank_id' : 'bank_id';
                            $pdpCashKey = $pdpPrefix !== '' ? $pdpPrefix.'cash_box_id' : 'cash_box_id';
                            $pdpPmKey = $pdpPrefix !== '' ? $pdpPrefix.'payment_method_id' : 'payment_method_id';
                            $pdpInitBank = old($pdpBankKey, $pdpPrefix !== '' ? ($model?->paid_at_source_bank_id ?? null) : ($model?->bank_id ?? null));
                            $pdpInitCash = old($pdpCashKey, $pdpPrefix !== '' ? ($model?->paid_at_source_cash_box_id ?? null) : ($model?->cash_box_id ?? null));
                            $pdpInitPm = old($pdpPmKey, $pdpPrefix !== '' ? null : $val);
                        @endphp
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <x-accounting::payment-destination-picker
                            :context="$pdpCtx"
                            :catalog-url="$pdpUrl"
                            name-prefix="{{ $pdpPrefix }}"
                            :setup-routes="$pdpSetup"
                            :i18n="$pdpI18n"
                            :initial-payment-method-id="$pdpInitPm"
                            :initial-bank-id="$pdpInitBank"
                            :initial-cash-box-id="$pdpInitCash"
                            :initial-cheque-id="old('cheque_id', $model?->cheque_id)"
                            :initial-pos-terminal-id="old('pos_terminal_id', $model?->pos_terminal_id ?? null)"
                            :initial-wallet-id="old('wallet_id', $model?->wallet_id ?? null)"
                        />
                        @error('payment_method_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'ajax_customer_optional_select')
                        @php
                            $cuUrl = $customerSearchUrl ?? route('admin.accounting.suppliers.search-customers');
                            $selCu = $val;
                            $initCu = !empty($linkedCustomerSelectInitialText) ? $linkedCustomerSelectInitialText : '';
                        @endphp
                        <label class="form-label" for="fld-{{ $key }}">{{ $label }}</label>
                        <p class="text-muted small mb-2">{{ trans('accounting::accounting.supplier.customer_link_help') }}</p>
                        <div class="card border-primary border-opacity-50 js-accounting-card-picker"
                             data-search-url="{{ $cuUrl }}"
                             data-placeholder="{{ trans('accounting::accounting.supplier.customer_link_placeholder') }}"
                             data-initial-id="{{ ($selCu !== null && $selCu !== '' && (string) $selCu !== '0') ? (string) $selCu : '' }}"
                             data-initial-text="{{ $initCu !== '' ? $initCu : (($selCu !== null && $selCu !== '' && (string) $selCu !== '0') ? ('#'.$selCu) : '') }}">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between d-none mb-2" data-selected-box>
                                    <div>
                                        <div class="fw-semibold text-success" data-selected-text></div>
                                        <small class="text-muted" data-selected-id></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" data-clear-selection><i class="ph-x"></i></button>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                    <input type="text" class="form-control" data-search-input autocomplete="off" placeholder="{{ trans('accounting::accounting.supplier.customer_link_placeholder') }}">
                                    <input type="hidden" name="{{ $key }}" id="fld-{{ $key }}" value="{{ ($selCu !== null && $selCu !== '' && (string) $selCu !== '0') ? (string) $selCu : '' }}">
                                </div>
                                <div class="list-group mt-2 d-none" data-search-results></div>
                            </div>
                        </div>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'ajax_party_optional_select')
                        @php
                            $pUrl = $partySearchUrl ?? route('admin.accounting.suppliers.search-parties');
                            $selP = $val;
                            $initP = !empty($partySelectInitialText) ? $partySelectInitialText : '';
                            $dynamicLabels = is_array($chequeDynamicLabels ?? null) ? $chequeDynamicLabels : [];
                            $receivedCounterparty = (string) data_get($dynamicLabels, 'received.counterparty', $label);
                            $issuedCounterparty = (string) data_get($dynamicLabels, 'issued.counterparty', $label);
                            $receivedHint = (string) data_get($dynamicLabels, 'received.hint', trans('accounting::accounting.supplier.party_link_help'));
                            $issuedHint = (string) data_get($dynamicLabels, 'issued.hint', trans('accounting::accounting.supplier.party_link_help'));
                        @endphp
                        <label class="form-label"
                               for="fld-{{ $key }}"
                               data-cheque-counterparty-label
                               data-label-received="{{ $receivedCounterparty }}"
                               data-label-issued="{{ $issuedCounterparty }}">{{ $label }}</label>
                        <p class="text-muted small mb-2"
                           data-cheque-counterparty-hint
                           data-hint-received="{{ $receivedHint }}"
                           data-hint-issued="{{ $issuedHint }}">{{ trans('accounting::accounting.supplier.party_link_help') }}</p>
                        <div class="card border-primary border-opacity-50 js-accounting-card-picker"
                             data-search-url="{{ $pUrl }}"
                             data-placeholder="{{ trans('accounting::accounting.supplier.party_link_placeholder') }}"
                             data-initial-id="{{ ($selP !== null && $selP !== '' && (string) $selP !== '0') ? (string) $selP : '' }}"
                             data-initial-text="{{ $initP !== '' ? $initP : (($selP !== null && $selP !== '' && (string) $selP !== '0') ? ('#'.$selP) : '') }}">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between d-none mb-2" data-selected-box>
                                    <div>
                                        <div class="fw-semibold text-success" data-selected-text></div>
                                        <small class="text-muted" data-selected-id></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" data-clear-selection><i class="ph-x"></i></button>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                    <input type="text" class="form-control" data-search-input autocomplete="off" placeholder="{{ trans('accounting::accounting.supplier.party_link_placeholder') }}">
                                    <input type="hidden" name="{{ $key }}" id="fld-{{ $key }}" value="{{ ($selP !== null && $selP !== '' && (string) $selP !== '0') ? (string) $selP : '' }}">
                                </div>
                                <div class="list-group mt-2 d-none" data-search-results></div>
                            </div>
                        </div>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'customer_payment_customer_picker')
                        @php
                            $cSearchUrl = $customerPaymentSearchUrl ?? route('admin.accounting.customer-invoices.search-customers');
                            $selC = $val;
                            $initC = $customerPaymentSelectInitialText ?? '';
                            $initialLabel = $initC !== '' ? $initC : (($selC !== null && $selC !== '') ? ('#'.$selC) : '');
                        @endphp
                        <label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <div class="card border-primary border-opacity-50 customer-payment-customer-picker js-customer-payment-customer-picker"
                             data-search-url="{{ $cSearchUrl }}"
                             data-placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}"
                             data-initial-id="{{ $selC !== null ? (string) $selC : '' }}"
                             data-initial-text="{{ $initialLabel }}">
                            <div class="card-body p-3">
                                <div class="customer-payment-customer-picker__selected d-none" data-selected-box>
                                    <div>
                                        <div class="fw-semibold text-success" data-selected-text></div>
                                        <small class="text-muted" data-selected-id></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" data-clear-selection>
                                        <i class="ph-x"></i>
                                    </button>
                                </div>
                                <div class="input-group mt-2">
                                    <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                    <input type="text"
                                           class="form-control"
                                           data-search-input
                                           placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}"
                                           autocomplete="off">
                                    <input type="hidden"
                                           name="{{ $key }}"
                                           id="fld-{{ $key }}"
                                           value="{{ ($selC !== null && $selC !== '') ? (string) $selC : '' }}">
                                </div>
                                <div class="list-group mt-2 d-none customer-payment-customer-picker__results" data-search-results></div>
                            </div>
                        </div>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'ajax_customer_select')
                        @php
                            $defaultSearchUrl = (($formSlug ?? '') === 'customer_payments')
                                ? ($customerPaymentSearchUrl ?? route('admin.accounting.customer-invoices.search-customers'))
                                : ($customerInvoiceSearchUrl ?? route('admin.accounting.customer-invoices.search-customers'));
                            $selectedIdForPicker = (($formSlug ?? '') === 'cheques' && $key === 'party_id')
                                ? (($chequeCounterpartyCustomerId ?? '') !== '' ? $chequeCounterpartyCustomerId : (string) ($val ?? ''))
                                : $val;
                            $selectedTextForPicker = (($formSlug ?? '') === 'cheques' && $key === 'party_id')
                                ? ($chequeCounterpartySelectInitialText ?? '')
                                : (($key === 'customer_id' && ! empty($customerSelectInitialText))
                                    ? $customerSelectInitialText
                                    : (($key === 'customer_id' && ($formSlug ?? '') === 'customer_payments' && ! empty($customerPaymentSelectInitialText))
                                        ? $customerPaymentSelectInitialText
                                        : ''));
                        @endphp
                        <x-accounting::sales-customer-picker
                            name="{{ $key }}"
                            id="fld-{{ $key }}"
                            :required="$required"
                            :search-url="$defaultSearchUrl"
                            :selected-id="$selectedIdForPicker"
                            :selected-customer-id="(($formSlug ?? '') === 'cheques' && $key === 'party_id') ? ($chequeCounterpartyCustomerRecordId ?? '') : $selectedIdForPicker"
                            :selected-text="$selectedTextForPicker"
                            :create-url="$customerQuickCreateUrl ?? null"
                            :type-options="$customerQuickCreateTypeOptions ?? []"
                            :currency-options="$customerQuickCreateCurrencyOptions ?? []"
                            :default-currency="$defaultCurrency ?? 'IRT'"
                            :label="$label"
                        />
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'ajax_supplier_select')
                        @php
                            $supplierSearchUrl = $supplierSearchUrlOverride ?? route('admin.accounting.supplier-invoices.search-suppliers');
                            $selS = $val;
                            $initS = (($key === 'supplier_id' && ! empty($supplierSelectInitialText)) ? $supplierSelectInitialText : '');
                        @endphp
                        <label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <div class="card border-primary border-opacity-50 js-accounting-card-picker"
                             data-search-url="{{ $supplierSearchUrl }}"
                             data-placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}"
                             data-initial-id="{{ ($selS !== null && $selS !== '') ? (string) $selS : '' }}"
                             data-initial-text="{{ $initS !== '' ? $initS : (($selS !== null && $selS !== '') ? ('#'.$selS) : '') }}">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between d-none mb-2" data-selected-box>
                                    <div>
                                        <div class="fw-semibold text-success" data-selected-text></div>
                                        <small class="text-muted" data-selected-id></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" data-clear-selection><i class="ph-x"></i></button>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                    <input type="text" class="form-control" data-search-input autocomplete="off" placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}">
                                    <input type="hidden" name="{{ $key }}" id="fld-{{ $key }}" value="{{ ($selS !== null && $selS !== '') ? (string) $selS : '' }}" @if($required) required @endif>
                                </div>
                                <div class="list-group mt-2 d-none" data-search-results></div>
                            </div>
                        </div>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @elseif($widget === 'ajax_supplier_invoice_select')
                        @php
                            $depField = (string) ($row['depends_on_field'] ?? 'supplier_id');
                            $invSearchUrl = trim((string) ($row['supplier_invoice_search_url'] ?? ''));
                            if ($invSearchUrl === '') {
                                $invSearchUrl = route('admin.accounting.supplier-invoices.search-invoices');
                            }
                            $depSelector = '#fld-'.$depField;
                            $selInv = $val;
                            $initInvText = ($key === 'supplier_invoice_id' && ! empty($supplierInvoiceSelectInitialText)) ? $supplierInvoiceSelectInitialText : '';
                        @endphp
                        <label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <select name="{{ $key }}" id="fld-{{ $key }}"
                                class="form-select accounting-supplier-invoice-select2 @error($key) is-invalid @enderror"
                                data-search-url="{{ $invSearchUrl }}"
                                data-depends-on="{{ $depSelector }}"
                                data-placeholder-no-supplier="{{ e(trans('accounting::accounting.structured_resource_forms.select_supplier_first')) }}"
                                data-placeholder="{{ e(trans('accounting::accounting.structured_resource_forms.reference_invoice_placeholder')) }}"
                                @if($required) required @endif>
                            @if($selInv === null || $selInv === '' || (string) $selInv === '0')
                                @if($effectiveSupplierIdForDeps !== null && $effectiveSupplierIdForDeps !== '' && (string) $effectiveSupplierIdForDeps !== '0')
                                    <option value="" selected>{{ trans('accounting::accounting.structured_resource_forms.reference_invoice_placeholder') }}</option>
                                @else
                                    <option value="" selected>{{ trans('accounting::accounting.structured_resource_forms.select_supplier_first') }}</option>
                                @endif
                            @else
                                <option value="{{ $selInv }}" selected>{{ $initInvText !== '' ? $initInvText : ('#'.$selInv) }}</option>
                            @endif
                        </select>
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @else
                        <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="{{ $key }}" id="fld-{{ $key }}" class="form-control @error($key) is-invalid @enderror"
                               value="{{ is_scalar($val) || $val === null ? (string) ($val ?? '') : '' }}" @if($required) required @endif
                               @if((($formSlug ?? '') === 'supplier_invoices' || ($formSlug ?? '') === 'customer_invoices') && $key === 'invoice_number' && !empty($invoiceNumberUniquenessUrl))
                                   data-invoice-unique-url="{{ $invoiceNumberUniquenessUrl }}"
                                   @if(!empty($isEdit) && isset($model) && $model)
                                       data-invoice-exclude-id="{{ (int) $model->getKey() }}"
                                   @endif
                               @endif
                               @if(($formSlug ?? '') === 'purchase_orders' && $key === 'po_number' && !empty($poNumberUniquenessUrl))
                                   data-po-unique-url="{{ $poNumberUniquenessUrl }}"
                                   @if(!empty($isEdit) && isset($model) && $model)
                                       data-po-exclude-id="{{ (int) $model->getKey() }}"
                                   @endif
                               @endif
                               @if((($formSlug ?? '') === 'supplier_payments' || ($formSlug ?? '') === 'customer_payments') && $key === 'payment_number')
                                   data-payment-number-unique-url="{{ ($formSlug ?? '') === 'customer_payments' ? $customerPaymentNumberUniquenessUrl : $supplierPaymentNumberUniquenessUrl }}"
                                   @if(!empty($isEdit) && isset($model) && $model)
                                       data-payment-number-exclude-id="{{ (int) $model->getKey() }}"
                                   @endif
                               @endif>
                        @if((($formSlug ?? '') === 'supplier_invoices' || ($formSlug ?? '') === 'customer_invoices') && $key === 'invoice_number' && !empty($invoiceNumberUniquenessUrl))
                            <div class="invalid-feedback d-none" id="fld-invoice_number-unique-feedback"></div>
                        @endif
                        @if(($formSlug ?? '') === 'purchase_orders' && $key === 'po_number' && !empty($poNumberUniquenessUrl))
                            <div class="invalid-feedback d-none" id="fld-po_number-unique-feedback"></div>
                        @endif
                        @if((($formSlug ?? '') === 'supplier_payments' && !empty($supplierPaymentNumberUniquenessUrl) || (($formSlug ?? '') === 'customer_payments' && !empty($customerPaymentNumberUniquenessUrl))) && $key === 'payment_number')
                            <div class="invalid-feedback d-none" id="fld-payment_number-unique-feedback"></div>
                            <div class="valid-feedback d-none" id="fld-payment_number-unique-success"></div>
                        @endif
                        @if(($formSlug ?? '') === 'suppliers' && $key === 'name')
                            <div class="form-text">{{ trans('accounting::accounting.supplier.name_party_hint') }}</div>
                        @endif
                        @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
            @endforeach

            @if($supplierInvoicePostedLocked)
                </fieldset>
            @endif

            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                @if(! $supplierInvoicePostedLocked)
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ph-floppy-disk-back me-1"></i>{{ trans('accounting::accounting.structured_resource_forms.save') }}
                    </button>
                @else
                    <span class="text-muted small align-self-center">{{ trans('accounting::accounting.supplier_invoice.items_locked_document') }}</span>
                @endif
                <a href="{{ route($indexRoute) }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.structured_resource_forms.cancel') }}</a>
            </div>
            </form>
        </div>
    </div>

    @if(($formSlug ?? '') === 'manual_journals' && ! empty($isEdit) && isset($model) && $model instanceof \RMS\Accounting\Models\ManualJournal)
        @include('accounting::admin.manual_journals._lines_section')
    @endif

    @if(($formSlug ?? '') === 'cheques' && !empty($isEdit) && isset($model) && $model instanceof \RMS\Accounting\Models\Cheque)
        <div class="card border-0 shadow-sm border-start border-4 border-primary border-opacity-50 mt-3">
            <div class="card-header bg-light border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.cheques.actions_card.title') }}</h6>
                <span class="badge bg-secondary">{{ trans('accounting::accounting.fields.status') }}: {{ trans('accounting::accounting.cheque_statuses.'.($model->status ?? 'pending')) }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">{{ trans('accounting::accounting.cheques.actions_card.subtitle') }}</p>

                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">{{ trans('accounting::accounting.cheques.actions_card.cash_title') }}</h6>
                            <p class="text-muted small mb-3">{{ trans('accounting::accounting.cheques.actions_card.cash_help') }}</p>
                            @if(!empty($chequeCanCash))
                                <form method="post" action="{{ $chequeCashActionUrl }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ph-check-circle me-1"></i>{{ trans('accounting::accounting.cheques.actions_card.cash_btn') }}
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-light btn-sm" disabled>
                                    {{ trans('accounting::accounting.cheques.actions_card.cash_not_available') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">{{ trans('accounting::accounting.cheques.actions_card.bounce_title') }}</h6>
                            <p class="text-muted small mb-3">{{ trans('accounting::accounting.cheques.actions_card.bounce_help') }}</p>
                            @if(!empty($chequeCanBounce))
                                <form method="post" action="{{ $chequeBounceActionUrl }}" class="row g-2">
                                    @csrf
                                    <div class="col-12">
                                        <label class="form-label mb-1">{{ trans('accounting::accounting.cheques.actions_card.bounce_reason_label') }}</label>
                                        <textarea name="bounce_reason" class="form-control @error('bounce_reason') is-invalid @enderror" rows="2">{{ old('bounce_reason') }}</textarea>
                                        @error('bounce_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1">{{ trans('accounting::accounting.cheques.actions_card.penalty_amount_label') }}</label>
                                        <input type="text" name="penalty_amount" class="form-control @error('penalty_amount') is-invalid @enderror" value="{{ old('penalty_amount') }}" inputmode="decimal">
                                        @error('penalty_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label mb-1">{{ trans('accounting::accounting.cheques.actions_card.penalty_account_label') }}</label>
                                        <select name="penalty_account_id" class="form-select @error('penalty_account_id') is-invalid @enderror">
                                            <option value="">{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}</option>
                                            @foreach(($chequePenaltyAccountOptions ?? []) as $accId => $accLabel)
                                                <option value="{{ $accId }}" @selected((string) old('penalty_account_id') === (string) $accId)>{{ $accLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('penalty_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1">{{ trans('accounting::accounting.cheques.actions_card.penalty_notes_label') }}</label>
                                        <textarea name="penalty_notes" class="form-control @error('penalty_notes') is-invalid @enderror" rows="2">{{ old('penalty_notes') }}</textarea>
                                        @error('penalty_notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="ph-warning-circle me-1"></i>{{ trans('accounting::accounting.cheques.actions_card.bounce_btn') }}
                                        </button>
                                    </div>
                                </form>
                            @else
                                <button type="button" class="btn btn-light btn-sm" disabled>
                                    {{ trans('accounting::accounting.cheques.actions_card.bounce_not_available') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'debit_notes')
        @php
            $debitNoteReferenceInvoicePreviewUrlTemplate = $debitNoteReferenceInvoicePreviewUrlTemplate ?? '';
        @endphp
        <div class="card border-0 shadow-sm border-start border-4 border-info border-opacity-50 mt-3 d-none" id="debit-note-reference-invoice-card">
            <div class="card-header bg-light border-bottom py-3">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.debit_note_form.reference_preview_title') }}</h6>
                <small class="text-muted d-block mt-1">{{ trans('accounting::accounting.debit_note_form.reference_preview_subtitle') }}</small>
            </div>
            <div class="card-body p-0">
                <div id="debit-note-reference-invoice-mount" class="p-3"
                     data-preview-template="{{ e($debitNoteReferenceInvoicePreviewUrlTemplate) }}"
                     data-msg-loading="{{ e(trans('accounting::accounting.debit_note_form.reference_preview_loading')) }}"
                     data-msg-failed="{{ e(trans('accounting::accounting.debit_note_form.reference_preview_failed')) }}"
                     data-msg-placeholder="{{ e(trans('accounting::accounting.debit_note_form.reference_preview_placeholder')) }}">
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.debit_note_form.reference_preview_placeholder') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'credit_notes')
        <div class="card border-0 shadow-sm border-start border-4 border-info border-opacity-50 mt-3 @if(empty($creditNoteContextInvoice)) d-none @endif" id="credit-note-reference-invoice-card">
            <div class="card-header bg-light border-bottom py-3">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.credit_note_form.reference_preview_title') }}</h6>
                <small class="text-muted d-block mt-1">{{ trans('accounting::accounting.credit_note_form.reference_preview_subtitle') }}</small>
            </div>
            <div class="card-body p-0">
                <div id="credit-note-reference-invoice-mount" class="p-3"
                     data-preview-template="{{ e($creditNoteReferenceInvoicePreviewUrlTemplate) }}"
                     data-msg-loading="{{ e(trans('accounting::accounting.credit_note_form.reference_preview_loading')) }}"
                     data-msg-failed="{{ e(trans('accounting::accounting.credit_note_form.reference_preview_failed')) }}"
                     data-msg-placeholder="{{ e(trans('accounting::accounting.credit_note_form.reference_preview_placeholder')) }}">
                    @if(!empty($creditNoteContextInvoice))
                        @include('accounting::admin.customer_invoices._credit_note_reference_preview', ['invoice' => $creditNoteContextInvoice])
                    @else
                        <p class="text-muted small mb-0">{{ trans('accounting::accounting.credit_note_form.reference_preview_placeholder') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'supplier_invoices' && ! empty($isEdit) && ! empty($supplierInvoiceItemsFragmentUrl))
        @include('accounting::admin.form._accounting_purchase_summary_cards')
        <div class="card border-0 shadow-sm border-start border-4 border-warning border-opacity-50 mt-3">
            <div class="card-header bg-light border-bottom py-3">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.supplier_invoice.items_heading') }}</h6>
            </div>
            <div class="card-body p-0">
                <div id="supplier-invoice-items-mount" class="p-3"
                     data-items-url="{{ $supplierInvoiceItemsFragmentUrl }}"
                     @if(! empty($supplierInvoiceItemsStoreUrl)) data-items-store-url="{{ $supplierInvoiceItemsStoreUrl }}" @endif
                     data-load-error="{{ e(trans('accounting::accounting.supplier_invoice.items_load_failed')) }}">
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.supplier_invoice.items_loading') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'customer_invoices' && ! empty($isEdit))
        @include('accounting::admin.form._accounting_purchase_summary_cards')
    @endif

    @if(($formSlug ?? '') === 'customer_invoices' && ! empty($isEdit) && ! empty($customerInvoiceItemsFragmentUrl))
        <div class="card border-0 shadow-sm border-start border-4 border-info border-opacity-50 mt-3">
            <div class="card-header bg-light border-bottom py-3">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.customer_invoice.items_heading') }}</h6>
            </div>
            <div class="card-body p-0">
                <div id="customer-invoice-items-mount" class="p-3"
                     data-items-url="{{ $customerInvoiceItemsFragmentUrl }}"
                     @if(! empty($customerInvoiceItemsStoreUrl)) data-items-store-url="{{ $customerInvoiceItemsStoreUrl }}" @endif
                     data-load-error="{{ e(trans('accounting::accounting.customer_invoice.items_load_failed')) }}">
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.customer_invoice.items_loading') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'purchase_orders' && ! empty($isEdit) && ! empty($purchaseOrderItemsFragmentUrl))
        @include('accounting::admin.form._accounting_purchase_summary_cards')
        <div class="card border-0 shadow-sm border-start border-4 border-warning border-opacity-50 mt-3">
            <div class="card-header bg-light border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.purchase_order.items_heading') }}</h6>
                @if(! empty($purchaseOrderWarehouseReceiptPdfUrl))
                    <a href="{{ $purchaseOrderWarehouseReceiptPdfUrl }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                        <i class="ph-file-pdf me-1"></i>{{ trans('accounting::accounting.purchase_order.warehouse_receipt_btn') }}
                    </a>
                @endif
            </div>
            <div class="card-body p-0">
                <div id="purchase-order-items-mount" class="p-3"
                     data-items-url="{{ $purchaseOrderItemsFragmentUrl }}"
                     @if(! empty($purchaseOrderItemsStoreUrl)) data-items-store-url="{{ $purchaseOrderItemsStoreUrl }}" @endif
                     data-load-error="{{ e(trans('accounting::accounting.purchase_order.items_load_failed')) }}">
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.purchase_order.items_loading') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(($formSlug ?? '') === 'customer_invoices')
        @include('accounting::components.collapse_help_card', [
            'collapseId' => 'accounting-customer-invoice-vat-help',
            'toggleLabel' => trans('accounting::accounting.customer_invoice_form_help.toggle_label'),
            'title' => trans('accounting::accounting.customer_invoice_form_help.title'),
            'paragraphs' => trans('accounting::accounting.customer_invoice_form_help.paragraphs'),
            'cardClass' => 'mt-3',
        ])
    @endif

    @if(($formSlug ?? '') === 'cheques' && (!empty($chequeClearingSetupMissing) || (int)($chequeActiveChequebooksCount ?? 0) <= 0))
        @php
            $chequeGuideParagraphs = [];
            if (!empty($chequeClearingSetupMissing)) {
                $chequeGuideParagraphs[] = trans('accounting::accounting.cheques.setup_guide.clearing_intro');
                if (!empty($chequeSetupIssues) && is_array($chequeSetupIssues)) {
                    foreach ($chequeSetupIssues as $issueText) {
                        $chequeGuideParagraphs[] = '• '.(string) $issueText;
                    }
                }
                $chequeGuideParagraphs[] = trans('accounting::accounting.cheques.setup_guide.clearing_action_settings');
                $chequeGuideParagraphs[] = trans('accounting::accounting.cheques.setup_guide.clearing_action_accounts');
            }
            if ((int)($chequeActiveChequebooksCount ?? 0) <= 0) {
                $chequeGuideParagraphs[] = trans('accounting::accounting.cheques.setup_guide.chequebook_intro');
                $chequeGuideParagraphs[] = trans('accounting::accounting.cheques.setup_guide.chequebook_action');
            }
        @endphp
        @include('accounting::components.collapse_help_card', [
            'collapseId' => 'accounting-cheques-setup-guide',
            'toggleLabel' => trans('accounting::accounting.cheques.setup_guide.toggle_label'),
            'title' => trans('accounting::accounting.cheques.setup_guide.title'),
            'paragraphs' => $chequeGuideParagraphs,
            'cardClass' => 'mt-3',
        ])
        <div class="card border-0 shadow-sm mt-2">
            <div class="card-body py-2 d-flex flex-wrap gap-2">
                <a href="{{ $chequeSettingsUrl ?? route('admin.accounting.settings.index') }}" class="btn btn-sm btn-outline-primary">
                    {{ trans('accounting::accounting.cheques.setup_warning_settings_btn') }}
                </a>
                <a href="{{ $chequeAccountsUrl ?? route('admin.accounting.accounts.index') }}" class="btn btn-sm btn-outline-secondary">
                    {{ trans('accounting::accounting.cheques.setup_warning_accounts_btn') }}
                </a>
                @if((int)($chequeActiveChequebooksCount ?? 0) <= 0)
                    <a href="{{ route('admin.accounting.chequebooks.create') }}" class="btn btn-sm btn-outline-info">
                        {{ trans('accounting::accounting.cheques.setup_guide.chequebook_button') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
