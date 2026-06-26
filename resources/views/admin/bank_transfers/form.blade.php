{{-- فرم اختصاصی ثبت/ویرایش انتقال بین بانکی — rmscms/accounting --}}
@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? config('app.name'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\BankTransfer|null $bankTransfer */
    $bankTransfer = $bankTransfer ?? null;
    $transferSummary = $transferSummary ?? null;
    $treasuryOptions = $treasuryOptions ?? [];
    $defaultCurrency = $defaultCurrency ?? 'IRR';
    $amountDecimalPlaces = (int) ($amountDecimalPlaces ?? 0);
    $pdNow = function_exists('persian_date') ? \RMS\Helper\persian_date(now(), 'Y/m/d') : now()->format('Y-m-d');
    $pdTransfer = ($isEdit && $bankTransfer && $bankTransfer->transfer_date)
        ? (function_exists('persian_date') ? \RMS\Helper\persian_date($bankTransfer->transfer_date, 'Y/m/d') : $bankTransfer->transfer_date->format('Y-m-d'))
        : $pdNow;
    $amountDefault = '';
    if ($isEdit && $bankTransfer && $bankTransfer->amount !== null) {
        $amountDefault = number_format((float) $bankTransfer->amount, $amountDecimalPlaces, '.', ',');
    }
    $feeDefault = '';
    if ($isEdit && $bankTransfer && $bankTransfer->transfer_fee !== null) {
        $feeDefault = number_format((float) $bankTransfer->transfer_fee, $amountDecimalPlaces, '.', ',');
    }
    $fromSelectionDefault = old(
        'from_treasury',
        ($bankTransfer
            ? (($bankTransfer->from_treasury_type ?: ($bankTransfer->from_bank_id ? \RMS\Accounting\Models\BankTransfer::TREASURY_TYPE_BANK : ''))
                . ':'
                . ($bankTransfer->from_treasury_id ?: $bankTransfer->from_bank_id))
            : '')
    );
    $toSelectionDefault = old(
        'to_treasury',
        ($bankTransfer
            ? (($bankTransfer->to_treasury_type ?: ($bankTransfer->to_bank_id ? \RMS\Accounting\Models\BankTransfer::TREASURY_TYPE_BANK : ''))
                . ':'
                . ($bankTransfer->to_treasury_id ?: $bankTransfer->to_bank_id))
            : '')
    );
    $canEditCore = ! $isEdit || ($bankTransfer && $bankTransfer->status === 'pending');
@endphp
<div class="container-fluid" data-role="bank-transfer-form-page">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.bank_transfer_form.title_edit') : trans('accounting::accounting.bank_transfer_form.title') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.bank_transfer_form.page_subtitle') }}</small>
                @if($isEdit && $bankTransfer)
                    <small class="text-muted d-block mt-1">
                        {{ trans('accounting::accounting.bank_transfer_form.transfer_number_label') }}:
                        <span class="font-monospace">{{ $bankTransfer->transfer_number }}</span>
                        — {{ trans('accounting::accounting.bank_transfer_form.status_label') }}:
                        <span class="badge bg-secondary">{{ trans('accounting::accounting.bank_transfer_form.statuses.'.$bankTransfer->status) }}</span>
                    </small>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.bank-transfers.index') }}" class="btn btn-light btn-sm">
                    <i class="ph-list me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.back_to_list') }}
                </a>
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

            @if($isEdit && $bankTransfer && ! $canEditCore)
                <div class="alert alert-info mb-3">
                    {{ trans('accounting::accounting.bank_transfer_form.readonly_notice') }}
                </div>
            @endif

            <x-accounting::page-description class="mt-0 mb-3" :title="trans('accounting::accounting.bank_transfer_form.page_description_title')">
                @foreach(trans('accounting::accounting.bank_transfer_form.page_description_paragraphs') as $line)
                    <p class="mb-2">{{ $line }}</p>
                @endforeach
            </x-accounting::page-description>

            @php
                $showTransferForm = ! $isEdit || ($isEdit && $bankTransfer && $canEditCore);
            @endphp
            @if($showTransferForm)
                @if($isEdit && $bankTransfer)
                    <form method="post" action="{{ route('admin.accounting.bank-transfers.update', $bankTransfer) }}" class="row g-3">
                        @csrf
                        @method('PUT')
                @else
                    <form method="post" action="{{ route('admin.accounting.bank-transfers.store') }}" class="row g-3">
                        @csrf
                @endif

                <div class="col-12">
                    <h6 class="text-primary fw-semibold mb-2">
                        <i class="ph-arrows-left-right me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.section_treasury') }}
                    </h6>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.from_treasury') }} <span class="text-danger">*</span></label>
                    <select name="from_treasury" class="form-select enhanced-select @error('from_treasury') is-invalid @enderror" required
                            data-placeholder="{{ trans('accounting::accounting.bank_transfer_form.placeholders.from_treasury') }}">
                        <option value=""></option>
                        @foreach($treasuryOptions as $item)
                            <option value="{{ $item['key'] }}" @selected($fromSelectionDefault === $item['key'])>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    @error('from_treasury')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.to_treasury') }} <span class="text-danger">*</span></label>
                    <select name="to_treasury" class="form-select enhanced-select @error('to_treasury') is-invalid @enderror" required
                            data-placeholder="{{ trans('accounting::accounting.bank_transfer_form.placeholders.to_treasury') }}">
                        <option value=""></option>
                        @foreach($treasuryOptions as $item)
                            <option value="{{ $item['key'] }}" @selected($toSelectionDefault === $item['key'])>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    @error('to_treasury')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">
                        <i class="ph-currency-circle-dollar me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.section_amounts') }}
                    </h6>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.amount') }} <span class="text-danger">*</span></label>
                    <input type="text" name="amount" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                           class="form-control amount-decimal @error('amount') is-invalid @enderror"
                           value="{{ old('amount', $amountDefault) }}" autocomplete="off" required>
                    <div class="form-text">{{ trans('accounting::accounting.bank_transfer_form.hints.amount', ['currency' => $defaultCurrency]) }}</div>
                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.transfer_fee') }}</label>
                    <input type="text" name="transfer_fee" inputmode="decimal" data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}"
                           class="form-control amount-decimal @error('transfer_fee') is-invalid @enderror"
                           value="{{ old('transfer_fee', $feeDefault !== '' ? $feeDefault : '0') }}" autocomplete="off">
                    <div class="form-text">{{ trans('accounting::accounting.bank_transfer_form.hints.transfer_fee') }}</div>
                    @error('transfer_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-primary fw-semibold mb-2">
                        <i class="ph-calendar-blank me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.section_meta') }}
                    </h6>
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.transfer_date') }} <span class="text-danger">*</span></label>
                    <input type="text" name="transfer_date" class="form-control persian-datepicker @error('transfer_date') is-invalid @enderror"
                           data-format="YYYY/MM/DD" value="{{ old('transfer_date', $pdTransfer) }}" autocomplete="off" required>
                    @error('transfer_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.reference_number') }}</label>
                    <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" dir="ltr"
                           value="{{ old('reference_number', $bankTransfer?->reference_number) }}" maxlength="100" autocomplete="off">
                    @error('reference_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.bank_transfer_form.fields.description') }}</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                              placeholder="{{ trans('accounting::accounting.bank_transfer_form.placeholders.description') }}">{{ old('description', $bankTransfer?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ph-floppy-disk-back me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.save') }}
                    </button>
                    <a href="{{ route('admin.accounting.bank-transfers.index') }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.bank_transfer_form.cancel') }}</a>
                </div>
                </form>
            @endif

            @if($isEdit && $bankTransfer && ! $canEditCore && is_array($transferSummary))
                <div class="card border-primary border-opacity-25 mt-3">
                    <div class="card-header bg-primary bg-opacity-10">
                        <h6 class="mb-0">
                            <i class="ph-file-text me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.report_card_title') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.status') }}</div>
                                <div class="fw-semibold">{{ $transferSummary['status_label'] }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.transfer_number') }}</div>
                                <div class="fw-semibold font-monospace">{{ $bankTransfer->transfer_number }}</div>
                            </div>

                            <div class="col-md-6">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.from_treasury') }}</div>
                                <div class="fw-semibold">{{ $transferSummary['from_label'] }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.to_treasury') }}</div>
                                <div class="fw-semibold">{{ $transferSummary['to_label'] }}</div>
                            </div>

                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.transfer_date') }}</div>
                                <div class="fw-semibold">{{ optional($transferSummary['transfer_date'])->format('Y-m-d') ?: '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.value_date') }}</div>
                                <div class="fw-semibold">{{ optional($transferSummary['value_date'])->format('Y-m-d') ?: '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.processed_at') }}</div>
                                <div class="fw-semibold">{{ optional($transferSummary['processed_at'])->format('Y-m-d H:i') ?: '-' }}</div>
                            </div>

                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.amount') }}</div>
                                <div class="fw-semibold">{{ number_format((float) $transferSummary['amount'], $amountDecimalPlaces, '.', ',') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.transfer_fee') }}</div>
                                <div class="fw-semibold">{{ number_format((float) $transferSummary['fee'], $amountDecimalPlaces, '.', ',') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.reference_number') }}</div>
                                <div class="fw-semibold">{{ $transferSummary['reference_number'] !== '' ? $transferSummary['reference_number'] : '—' }}</div>
                            </div>

                            <div class="col-12">
                                <div class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.description') }}</div>
                                <div class="fw-semibold">{{ $transferSummary['description'] !== '' ? $transferSummary['description'] : '—' }}</div>
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                                <span class="small text-muted">{{ trans('accounting::accounting.bank_transfer_form.report_labels.document') }}</span>
                                @if(($transferSummary['document_id'] ?? 0) > 0)
                                    <span class="badge bg-success">{{ $transferSummary['document_number'] !== '' ? $transferSummary['document_number'] : ('#'.$transferSummary['document_id']) }}</span>
                                    <a href="{{ route('admin.accounting.documents.show', ['document' => $transferSummary['document_id']]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ph-arrow-square-out me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.report_view_document_btn') }}
                                    </a>
                                @else
                                    <span class="badge bg-secondary">{{ trans('accounting::accounting.bank_transfer_form.report_document_missing') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($isEdit && $bankTransfer && $bankTransfer->status === 'pending')
                <div class="card border-success border-opacity-25 mt-3">
                    <div class="card-body">
                        <h6 class="mb-2 text-success">
                            <i class="ph-seal-check me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.finalize_section_title') }}
                        </h6>
                        <p class="small text-muted mb-3">{{ trans('accounting::accounting.bank_transfer_form.finalize_section_hint') }}</p>
                        <form method="post" action="{{ route('admin.accounting.bank-transfers.complete', $bankTransfer) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="ph-check-circle me-1"></i>{{ trans('accounting::accounting.bank_transfer_form.complete_btn') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'accounting-bank-transfer-form-help',
        'toggleLabel' => trans('accounting::accounting.bank_transfer_form_help.toggle_label'),
        'title' => trans('accounting::accounting.bank_transfer_form_help.title'),
        'paragraphs' => trans('accounting::accounting.bank_transfer_form_help.paragraphs'),
        'cardClass' => 'mt-3',
    ])
</div>
@endsection
