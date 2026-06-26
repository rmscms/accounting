{{-- گردش کار اعتبار برگشتی: ذخیره → صدور → اعمال به فاکتور --}}
@php
    $cn = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\CreditNote) ? $model : null;
    $hasAmount = $cn && (float) ($cn->total_amount ?? 0) > 0;
@endphp
<div class="card border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-header bg-info bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-info text-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-note"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.credit_note.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.credit_note.card_sub') }}</small>
        </div>
    </div>
    <div class="card-body">
        @error('issue')
            <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
        @enderror
        @error('apply')
            <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
        @enderror
        @error('invoice_id')
            <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
        @enderror

        <ul class="list-unstyled small lh-lg mb-3">
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.credit_note.bullet1') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.credit_note.bullet2') }}</span></li>
            <li class="d-flex gap-2 mb-0"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.credit_note.bullet3') }}</span></li>
        </ul>

        @if(! $cn)
            <div class="alert alert-light border mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.credit_note.after_first_save') }}
            </div>
        @elseif($cn->isDraft())
            @php
                $applyInvoiceValue = old('invoice_id', $cn->customer_invoice_id);
                $applyInvoiceDisplay = ($applyInvoiceValue !== null && $applyInvoiceValue !== '' && (string) $applyInvoiceValue !== '0')
                    ? (int) $applyInvoiceValue
                    : '';
            @endphp
            @if(! $hasAmount)
                <div class="alert alert-info border border-info border-opacity-50 mb-0 small" role="status">
                    <strong class="d-block mb-1">{{ trans('accounting::accounting.structured_workflow.credit_note.amount_missing_title') }}</strong>
                    {{ trans('accounting::accounting.structured_workflow.credit_note.amount_missing_body') }}
                </div>
            @else
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2 border-top mb-3">
                    <p class="small text-muted mb-0">{{ trans('accounting::accounting.structured_workflow.credit_note.issue_hint') }}</p>
                    <form method="post" action="{{ route('admin.accounting.credit-notes.issue', ['id' => $cn->getKey()]) }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ph-seal-check me-1"></i>{{ trans('accounting::accounting.structured_workflow.credit_note.issue_btn') }}
                        </button>
                    </form>
                </div>
                <div class="border-top pt-3">
                    <p class="small text-muted mb-2">{{ trans('accounting::accounting.structured_workflow.credit_note.apply_intro_draft') }}</p>
                    <form method="post" action="{{ route('admin.accounting.credit-notes.apply', ['id' => $cn->getKey()]) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label small mb-1" for="credit-note-apply-invoice-id">{{ trans('accounting::accounting.structured_workflow.credit_note.apply_invoice_label') }}</label>
                            <input type="number" name="invoice_id" id="credit-note-apply-invoice-id" class="form-control form-control-sm" min="1"
                                   value="{{ $applyInvoiceDisplay !== '' ? $applyInvoiceDisplay : '' }}"
                                   placeholder="{{ trans('accounting::accounting.structured_workflow.credit_note.apply_invoice_placeholder') }}">
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="ph-check-circle me-1"></i>{{ trans('accounting::accounting.structured_workflow.credit_note.apply_btn') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @elseif($cn->isIssued())
            @php
                $applyInvoiceValue = old('invoice_id', $cn->applied_to_invoice_id ?: $cn->customer_invoice_id);
                $applyInvoiceDisplay = ($applyInvoiceValue !== null && $applyInvoiceValue !== '' && (string) $applyInvoiceValue !== '0')
                    ? (int) $applyInvoiceValue
                    : '';
            @endphp
            <div class="border-top pt-3">
                <p class="small text-muted mb-2">{{ trans('accounting::accounting.structured_workflow.credit_note.apply_intro') }}</p>
                <form method="post" action="{{ route('admin.accounting.credit-notes.apply', ['id' => $cn->getKey()]) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-1" for="credit-note-apply-invoice-id">{{ trans('accounting::accounting.structured_workflow.credit_note.apply_invoice_label') }}</label>
                        <input type="number" name="invoice_id" id="credit-note-apply-invoice-id" class="form-control form-control-sm" min="1"
                               value="{{ $applyInvoiceDisplay !== '' ? $applyInvoiceDisplay : '' }}"
                               placeholder="{{ trans('accounting::accounting.structured_workflow.credit_note.apply_invoice_placeholder') }}">
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="ph-link me-1"></i>{{ trans('accounting::accounting.structured_workflow.credit_note.apply_btn') }}
                        </button>
                    </div>
                </form>
            </div>
        @elseif($cn->isApplied())
            <div class="alert alert-success border border-success border-opacity-25 mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.credit_note.applied_notice') }}
            </div>
        @else
            <div class="alert alert-secondary mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.credit_note.other_status') }}
            </div>
        @endif
    </div>
</div>
