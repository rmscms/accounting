{{-- گردش کار یادداشت بدهکار: مبلغ → صدور → اعمال به فاکتور --}}
@php
    $dn = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\DebitNote) ? $model : null;
    $hasLineItems = $dn && $dn->relationLoaded('items') ? $dn->items->isNotEmpty() : ($dn && $dn->items()->exists());
    $hasAmount = $dn && ((float) $dn->total_amount > 0 || $hasLineItems);
@endphp
<div class="card border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-note-pencil"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.debit_note.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.debit_note.card_sub') }}</small>
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
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.debit_note.bullet1') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.debit_note.bullet2') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.debit_note.bullet3') }}</span></li>
            <li class="d-flex gap-2 mb-0"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.debit_note.bullet4') }}</span></li>
        </ul>

        @if(! $dn)
            <div class="alert alert-light border mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.debit_note.after_first_save') }}
            </div>
        @elseif($dn->isDraft())
            @if(! $hasAmount)
                <div class="alert alert-info border border-info border-opacity-50 mb-0 small" role="status">
                    <strong class="d-block mb-1">{{ trans('accounting::accounting.structured_workflow.debit_note.amount_missing_title') }}</strong>
                    {{ trans('accounting::accounting.structured_workflow.debit_note.amount_missing_body') }}
                </div>
            @else
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2 border-top">
                    <p class="small text-muted mb-0">{{ trans('accounting::accounting.structured_workflow.debit_note.issue_hint') }}</p>
                    <form method="post" action="{{ route('admin.accounting.debit-notes.issue', ['id' => $dn->getKey()]) }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-seal-check me-2"></i>{{ trans('accounting::accounting.structured_workflow.debit_note.issue_btn') }}
                        </button>
                    </form>
                </div>
            @endif
        @elseif($dn->isIssued())
            @php
                $applyInvoiceValue = old('invoice_id', $dn->supplier_invoice_id);
                $applyInvoiceDisplay = ($applyInvoiceValue !== null && $applyInvoiceValue !== '' && (string) $applyInvoiceValue !== '0')
                    ? (int) $applyInvoiceValue
                    : '';
            @endphp
            <div class="border-top pt-3">
                <p class="small text-muted mb-2">{{ trans('accounting::accounting.structured_workflow.debit_note.apply_intro') }}</p>
                <form method="post" action="{{ route('admin.accounting.debit-notes.apply', ['id' => $dn->getKey()]) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-1" for="debit-note-apply-invoice-id">{{ trans('accounting::accounting.structured_workflow.debit_note.apply_invoice_label') }}</label>
                        <input type="number" name="invoice_id" id="debit-note-apply-invoice-id" class="form-control form-control-sm" min="1"
                               value="{{ $applyInvoiceDisplay !== '' ? $applyInvoiceDisplay : '' }}"
                               placeholder="{{ trans('accounting::accounting.structured_workflow.debit_note.apply_invoice_placeholder') }}">
                        <div class="form-text">
                            @if($dn->supplier_invoice_id)
                                {{ trans('accounting::accounting.structured_workflow.debit_note.apply_prefilled_from_reference', ['id' => (int) $dn->supplier_invoice_id]) }}
                            @else
                                {{ trans('accounting::accounting.structured_workflow.debit_note.apply_invoice_help', ['supplier' => (string) ($dn->supplier?->name ?? '')]) }}
                            @endif
                            <span class="d-block mt-1">{{ trans('accounting::accounting.structured_workflow.debit_note.apply_invoice_override_hint') }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="ph-link me-1"></i>{{ trans('accounting::accounting.structured_workflow.debit_note.apply_btn') }}
                        </button>
                    </div>
                </form>
            </div>
        @elseif($dn->isApplied())
            <div class="alert alert-success border border-success border-opacity-25 mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.debit_note.applied_notice') }}
            </div>
        @else
            <div class="alert alert-secondary mb-0 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.debit_note.other_status') }}
            </div>
        @endif
    </div>
</div>
