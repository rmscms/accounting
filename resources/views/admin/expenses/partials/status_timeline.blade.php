{{-- تایم‌لاین تغییر وضعیت هزینه — فقط ویرایش --}}
@php
    /** @var \RMS\Accounting\Models\Expense $expense */
    $histories = $expense->statusHistories ?? collect();
    $statusOptions = $statusOptions ?? [];
    $statusLabel = function (?string $code) use ($statusOptions): string {
        if ($code === null || $code === '') {
            return '—';
        }

        return $statusOptions[$code] ?? $code;
    };
@endphp
@if($histories->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0">{{ trans('accounting::accounting.expense_create.timeline_title') }}</h6>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0 border-start border-2 border-primary border-opacity-25 ms-2 ps-3">
                @foreach($histories as $h)
                    <li class="position-relative mb-4 pb-1">
                        <span class="position-absolute top-0 start-0 translate-middle-x rounded-circle bg-primary p-1 ms-n1" style="width: 10px; height: 10px; margin-left: -2px;"></span>
                        <div class="small text-muted mb-1">
                            @if($h->created_at)
                                {{ $h->created_at->format('Y-m-d H:i') }}
                            @endif
                            @if($h->admin)
                                <span class="ms-1">· {{ trans('accounting::accounting.expense_create.timeline_by') }}: {{ $h->admin->name ?? $h->admin->email ?? ('#'.$h->admin_user_id) }}</span>
                            @elseif($h->admin_user_id)
                                <span class="ms-1">· #{{ $h->admin_user_id }}</span>
                            @endif
                        </div>
                        @if($h->from_status === $h->to_status && $h->note)
                            <div class="fw-medium">{{ $h->note }}</div>
                        @else
                            <div>
                                <span class="text-muted">{{ trans('accounting::accounting.expense_create.timeline_from') }}:</span>
                                {{ $statusLabel($h->from_status) }}
                                <span class="text-muted mx-1">→</span>
                                <span class="text-muted">{{ trans('accounting::accounting.expense_create.timeline_to') }}:</span>
                                {{ $statusLabel($h->to_status) }}
                            </div>
                            @if($h->note)
                                <div class="small text-muted mt-1">{{ $h->note }}</div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
