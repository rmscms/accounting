{{-- راهنمای ثبت پرداخت به تأمین‌کننده --}}
@php
    $pay = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\SupplierPayment) ? $model : null;
    $canVoid = $pay && (string) ($pay->status ?? '') === \RMS\Accounting\Models\SupplierPayment::STATUS_COMPLETED;
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-success bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-hand-coins"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.supplier_payment.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.supplier_payment.card_sub') }}</small>
        </div>
    </div>
    <div class="card-body small text-body-secondary lh-lg">
        <p class="mb-2">{{ trans('accounting::accounting.structured_workflow.supplier_payment.body1') }}</p>
        <p class="mb-0">{{ trans('accounting::accounting.structured_workflow.supplier_payment.body2') }}</p>
    </div>
</div>

@if($canVoid)
    <div class="card border-0 shadow-sm mb-3 border-start border-danger border-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-2 d-inline-flex">
                    <i class="ph-arrow-u-up-left fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_title') }}</h6>
                    <p class="text-muted small mb-0 lh-lg">{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_body') }}</p>
                </div>
            </div>
            @error('void_reason')
                <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
            @enderror
            <form method="post" action="{{ route('admin.accounting.supplier-payments.void', ['supplier_payment' => $pay->getKey()]) }}" class="border-top pt-3" onsubmit="return confirm(@json(trans('accounting::accounting.structured_workflow.supplier_payment.void_confirm')));">
                @csrf
                <label class="form-label small fw-semibold" for="supplier-payment-void-reason">{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_reason_label') }}</label>
                <textarea name="void_reason" id="supplier-payment-void-reason" class="form-control form-control-sm mb-2" rows="3" required maxlength="5000" placeholder="{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_reason_placeholder') }}">{{ old('void_reason') }}</textarea>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="ph-prohibit me-1"></i>{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_submit') }}
                </button>
            </form>
        </div>
    </div>
@elseif($pay && (string) ($pay->status ?? '') === \RMS\Accounting\Models\SupplierPayment::STATUS_VOIDED)
    <div class="alert alert-secondary border small mb-3" role="status">
        {{ trans('accounting::accounting.structured_workflow.supplier_payment.voided_notice') }}
        @if(filled($pay->void_reason))
            <div class="mt-2 text-body-secondary"><strong>{{ trans('accounting::accounting.structured_workflow.supplier_payment.void_reason_caption') }}</strong> {{ e((string) $pay->void_reason) }}</div>
        @endif
    </div>
@endif
