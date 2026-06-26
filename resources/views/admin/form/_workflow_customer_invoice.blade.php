{{-- راهنمای فاکتور فروش و مسیر دریافت --}}
@php
    $inv = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\CustomerInvoice) ? $model : null;
    $hasAccountingDocument = $inv && (int) ($inv->document_id ?? 0) > 0;
    $canPost = $inv && ! $hasAccountingDocument;
    $correctionTimeline = $customerInvoiceCorrectionsTimeline ?? collect();
@endphp
<div class="card border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-receipt"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.customer_invoice.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.customer_invoice.card_sub') }}</small>
        </div>
    </div>
    <div class="card-body">
        <ul class="list-unstyled small lh-lg mb-3">
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.customer_invoice.bullet1') }}</span></li>
            <li class="d-flex gap-2 mb-2"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.customer_invoice.bullet2') }}</span></li>
            <li class="d-flex gap-2 mb-0"><i class="ph-dot-outline text-primary mt-1"></i><span>{{ trans('accounting::accounting.structured_workflow.customer_invoice.bullet3') }}</span></li>
        </ul>
        @if($inv)
            <div class="alert alert-light border small py-2 mb-3">
                <div class="d-flex flex-wrap gap-3">
                    <span><strong>{{ trans('accounting::accounting.customer_invoice.settlement_mode') }}:</strong> {{ e((string) $inv->settlement_mode) }}</span>
                    <span><strong>{{ trans('accounting::accounting.invoice.payment_status') }}:</strong> {{ e((string) $inv->payment_status) }}</span>
                </div>
            </div>
        @endif
        @if($inv && $inv->customer_id)
            @php
                $recvUrl = route('admin.accounting.customer-payments.create', [], false)
                    . '?customer_id=' . urlencode((string) $inv->customer_id)
                    . '&customer_invoice_id=' . urlencode((string) $inv->getKey());
            @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                <a href="{{ $recvUrl }}" class="btn btn-primary">
                    <i class="ph-currency-circle-dollar me-2"></i>{{ trans('accounting::accounting.structured_workflow.customer_invoice.receive_cta') }}
                </a>
                <a href="{{ route('admin.accounting.customer-payments.index') }}" class="btn btn-light btn-sm border">
                    <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_workflow.customer_invoice.receive_list') }}
                </a>
            </div>
        @else
            <div class="alert alert-light border mb-0 small">
                {{ trans('accounting::accounting.structured_workflow.customer_invoice.receive_after_save') }}
            </div>
        @endif
    </div>
</div>

@if($canPost)
    <div class="card border-0 shadow-sm mb-3 border-start border-success border-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 d-inline-flex">
                    <i class="ph-seal-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.customer_invoice.post_document_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.structured_workflow.customer_invoice.post_document_body') }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('admin.accounting.customer-invoices.post-document', ['customer_invoice' => $inv->getKey()]) }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-success btn-lg px-4">
                    <i class="ph-check-circle me-2"></i>{{ trans('accounting::accounting.structured_workflow.customer_invoice.post_document_btn') }}
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
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.customer_invoice.posted_document_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.structured_workflow.customer_invoice.posted_document_body') }}</p>
                </div>
            </div>
            <a href="{{ route('admin.accounting.documents.show', ['document' => $inv->document_id]) }}" class="btn btn-primary btn-lg px-4">
                <i class="ph-arrow-square-out me-2"></i>{{ trans('accounting::accounting.structured_workflow.customer_invoice.posted_document_link') }}
            </a>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0 pb-3">
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.accounting.customer-invoices.reverse-and-replace', ['customer_invoice' => $inv->getKey()]) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="ph-arrow-counter-clockwise me-1"></i>{{ trans('accounting::accounting.customer_invoice.correction_reverse_replace_btn') }}
                    </button>
                </form>
                <form method="post" action="{{ route('admin.accounting.customer-invoices.adjustment', ['customer_invoice' => $inv->getKey()]) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="ph-note-pencil me-1"></i>{{ trans('accounting::accounting.customer_invoice.correction_adjustment_btn') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif

@if($inv && $correctionTimeline->count() > 0)
    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-secondary border-opacity-50">
        <div class="card-header bg-light border-bottom py-3">
            <h6 class="mb-1 fw-semibold">{{ trans('accounting::accounting.customer_invoice.correction_timeline_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.customer_invoice.correction_timeline_subtitle') }}</small>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @foreach($correctionTimeline as $entry)
                    <li class="pb-2 mb-2 border-bottom">
                        <div class="fw-semibold">{{ trans('accounting::accounting.customer_invoice.correction_actions.' . ($entry->action_type ?? 'adjustment')) }}</div>
                        <div class="small text-muted">
                            {{ optional($entry->created_at)->format('Y-m-d H:i') }}
                            @if($entry->admin)
                                — {{ $entry->admin->name ?? ('#'.$entry->admin_user_id) }}
                            @endif
                        </div>
                        <div class="small mt-1 d-flex flex-wrap gap-2">
                            @if((int) ($entry->source_document_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.documents.show', ['document' => (int) $entry->source_document_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.customer_invoice.correction_source_document') }} #{{ (int) $entry->source_document_id }}
                                </a>
                            @endif
                            @if((int) ($entry->target_document_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.documents.show', ['document' => (int) $entry->target_document_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.customer_invoice.correction_target_document') }} #{{ (int) $entry->target_document_id }}
                                </a>
                            @endif
                            @if((int) ($entry->target_invoice_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.customer-invoices.edit', ['customer_invoice' => (int) $entry->target_invoice_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.customer_invoice.correction_target_invoice') }} #{{ (int) $entry->target_invoice_id }}
                                </a>
                            @endif
                            @if((int) ($entry->credit_note_id ?? 0) > 0)
                                <a href="{{ route('admin.accounting.credit-notes.edit', ['credit_note' => (int) $entry->credit_note_id]) }}" class="text-decoration-none">
                                    {{ trans('accounting::accounting.customer_invoice.correction_adjustment_note') }} #{{ (int) $entry->credit_note_id }}
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
