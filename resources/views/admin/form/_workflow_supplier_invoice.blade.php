{{-- راهنمای فاکتور خرید و مسیر پرداخت --}}
@php
    $inv = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\SupplierInvoice) ? $model : null;
    $hasAccountingDocument = $inv && (int) ($inv->document_id ?? 0) > 0;
    $paidAtSource = $inv && (string) ($inv->settlement_mode ?? \RMS\Accounting\Models\SupplierInvoice::SETTLEMENT_ON_ACCOUNT) === \RMS\Accounting\Models\SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE;
    $correctionTimeline = $supplierInvoiceCorrectionsTimeline ?? collect();
@endphp
<div class="card border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-invoice"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.card_sub') }}</small>
        </div>
    </div>
    <div class="card-body">
        <ul class="list-unstyled small lh-lg mb-3">
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.bullet1') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.bullet2') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.bullet3') }}</span></li>
            <li class="d-flex gap-2 mb-0"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.bullet4') }}</span></li>
        </ul>
        @if($paidAtSource)
            <div class="alert alert-info border border-info border-opacity-50 mb-3 small" role="status">
                {{ trans('accounting::accounting.structured_workflow.supplier_invoice.paid_at_source_notice') }}
            </div>
        @endif
        @if($inv && $inv->supplier_id)
            @php
                $payUrl = route('admin.accounting.supplier-payments.create', [], false)
                    . '?supplier_id=' . urlencode((string) $inv->supplier_id)
                    . '&supplier_invoice_id=' . urlencode((string) $inv->getKey());
                $dnUrl = route('admin.accounting.debit-notes.create', [], false)
                    . '?supplier_id=' . urlencode((string) $inv->supplier_id)
                    . '&supplier_invoice_id=' . urlencode((string) $inv->getKey());
                $refundUrl = route('admin.accounting.supplier-refunds.create', [], false)
                    . '?supplier_id=' . urlencode((string) $inv->supplier_id)
                    . '&supplier_invoice_id=' . urlencode((string) $inv->getKey());
                if ((int) ($inv->purchase_order_id ?? 0) > 0) {
                    $refundUrl .= '&purchase_order_id=' . urlencode((string) $inv->purchase_order_id);
                }
            @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                @if(! $paidAtSource)
                    <a href="{{ $payUrl }}" class="btn btn-primary">
                        <i class="ph-bank me-2"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.pay_cta') }}
                    </a>
                    <a href="{{ route('admin.accounting.supplier-payments.index') }}" class="btn btn-light btn-sm border">
                        <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.pay_list') }}
                    </a>
                @endif
                <a href="{{ $dnUrl }}" class="btn btn-warning">
                    <i class="ph-note-pencil me-2"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.debit_note_cta') }}
                </a>
                <a href="{{ $refundUrl }}" class="btn btn-outline-secondary btn-sm border">
                    <i class="ph-arrow-u-up-left me-1"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.supplier_refund_cta') }}
                </a>
            </div>
        @else
            <div class="alert alert-light border mb-0 small">
                {{ trans('accounting::accounting.structured_workflow.supplier_invoice.pay_after_save') }}
            </div>
        @endif
    </div>
</div>

@if($inv && ! $hasAccountingDocument)
    <div class="card border-0 shadow-sm mb-3 border-start border-success border-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 d-inline-flex">
                    <i class="ph-seal-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.post_document_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.post_document_body') }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('admin.accounting.supplier-invoices.post-document', ['supplier_invoice' => $inv->getKey()]) }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-success btn-lg px-4">
                    <i class="ph-check-circle me-2"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.post_document_btn') }}
                </button>
            </form>
        </div>
    </div>
@endif

@if($hasAccountingDocument)
    <div class="card border-0 shadow-sm mb-3 border-start border-primary border-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 d-inline-flex">
                    <i class="ph-scroll fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.posted_document_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.structured_workflow.supplier_invoice.posted_document_body') }}</p>
                </div>
            </div>
            <a href="{{ route('admin.accounting.documents.show', ['document' => $inv->document_id]) }}" class="btn btn-primary btn-lg px-4">
                <i class="ph-arrow-square-out me-2"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.posted_document_link') }}
            </a>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0 pb-3">
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.accounting.supplier-invoices.reverse-and-replace', ['supplier_invoice' => $inv->getKey()]) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="ph-arrow-counter-clockwise me-1"></i>{{ trans('accounting::accounting.supplier_invoice.correction_reverse_replace_btn') }}
                    </button>
                </form>
                <form method="post" action="{{ route('admin.accounting.supplier-invoices.adjustment', ['supplier_invoice' => $inv->getKey()]) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="ph-note-pencil me-1"></i>{{ trans('accounting::accounting.supplier_invoice.correction_adjustment_btn') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif

@if($inv && $correctionTimeline->count() > 0)
    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-secondary border-opacity-50">
        <div class="card-header bg-light border-bottom py-3">
            <h6 class="mb-1 fw-semibold">{{ trans('accounting::accounting.supplier_invoice.correction_timeline_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.supplier_invoice.correction_timeline_subtitle') }}</small>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @foreach($correctionTimeline as $entry)
                    <li class="pb-2 mb-2 border-bottom">
                        <div class="fw-semibold">{{ trans('accounting::accounting.supplier_invoice.correction_actions.' . ($entry->action_type ?? 'adjustment')) }}</div>
                        <div class="small text-muted">
                            {{ optional($entry->created_at)->format('Y-m-d H:i') }}
                            @if($entry->admin)
                                — {{ $entry->admin->name ?? ('#'.$entry->admin_user_id) }}
                            @endif
                        </div>
                        <div class="small mt-1 d-flex flex-wrap gap-2">
                            @if((int) ($entry->source_document_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.documents.show', ['document' => (int) $entry->source_document_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.supplier_invoice.correction_source_document') }} #{{ (int) $entry->source_document_id }}
                                </a>
                            @endif
                            @if((int) ($entry->target_document_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.documents.show', ['document' => (int) $entry->target_document_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.supplier_invoice.correction_target_document') }} #{{ (int) $entry->target_document_id }}
                                </a>
                            @endif
                            @if((int) ($entry->target_invoice_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.supplier-invoices.edit', ['supplier_invoice' => (int) $entry->target_invoice_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.supplier_invoice.correction_target_invoice') }} #{{ (int) $entry->target_invoice_id }}
                                </a>
                            @endif
                            @if((int) ($entry->debit_note_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.debit-notes.edit', ['debit_note' => (int) $entry->debit_note_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.supplier_invoice.correction_adjustment_note') }} #{{ (int) $entry->debit_note_id }}
                                </a>
                            @endif
                        </div>
                        @if(!empty($entry->reason))
                            <div class="small mt-1">{{ $entry->reason }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
