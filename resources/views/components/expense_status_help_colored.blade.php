{{-- راهنمای رنگی وضعیت‌های هزینه — فقط داخل collapse پایین فرم --}}
@php
    $badgeClass = [
        'draft' => 'bg-secondary',
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-info',
        'rejected' => 'bg-danger',
        'paid' => 'bg-success',
        'cancelled' => 'bg-dark',
    ];
@endphp
<div class="expense-status-help-colored small">
    <h6 class="mb-2">
        <i class="ph-info text-primary me-1"></i>
        {{ trans('accounting::accounting.expense_create.status_help.box_title') }}
    </h6>
    <p class="text-muted mb-3 border-start border-primary border-3 ps-3 py-1 bg-primary bg-opacity-10 rounded-end">
        {{ trans('accounting::accounting.expense_create.status_help.intro') }}
    </p>
    <ul class="list-unstyled mb-0">
        @foreach($statusOptions as $value => $label)
            @php $cls = $badgeClass[$value] ?? 'bg-secondary'; @endphp
            <li class="mb-3 d-flex flex-wrap gap-2 align-items-start">
                <span class="badge {{ $cls }} align-self-start mt-1">{{ $label }}</span>
                <span class="text-body flex-grow-1" style="min-width: 12rem;">{{ trans('accounting::accounting.expense_create.status_help.items.'.$value) }}</span>
            </li>
        @endforeach
    </ul>
</div>
