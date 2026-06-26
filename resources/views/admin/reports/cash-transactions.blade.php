@extends('cms::admin.layout.index')
@section('title', $data['title'] ?? 'گزارش')
@section('content')
@php
    $statement = (array) ($data['statement'] ?? []);
    $periodStart = isset($statement['period']['start']) ? (string) $statement['period']['start'] : null;
    $periodEnd = isset($statement['period']['end']) ? (string) $statement['period']['end'] : null;
    $reportFromVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeFromDisplay(
        request('from_date'),
        request('start_date'),
        $periodStart
    );
    $reportToVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeToDisplay(
        request('to_date'),
        request('end_date'),
        $periodEnd
    );
    $cashboxId = (int) ($data['cashbox_id'] ?? request('cashbox_id', 0));
    $selectedEventType = (string) request('event_type', ($statement['filters']['event_type'] ?? ''));
    $selectedEventSource = (string) request('event_source', ($statement['filters']['event_source'] ?? ''));
@endphp
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end" action="{{ route('admin.accounting.reports.cash-transactions') }}">
                <div class="col-md-3">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_cashbox') }}</label>
                    <select name="cashbox_id" class="form-select" required>
                        <option value="">{{ trans('accounting::accounting.reports.treasury_statement.select_cashbox_placeholder') }}</option>
                        @foreach($data['cashboxes'] ?? [] as $c)
                            <option value="{{ $c->id }}" @selected($cashboxId === (int) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-accounting::date-range-filter
                    :from-value="$reportFromVal"
                    :to-value="$reportToVal"
                    from-col-class="col-md-2"
                    to-col-class="col-md-2"
                />
                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_event_type') }}</label>
                    <select name="event_type" class="form-select">
                        <option value="">{{ trans('accounting::accounting.reports.treasury_statement.filter_all') }}</option>
                        @foreach(['RECEIPT','PAYMENT','EXPENSE','SALE','PURCHASE','ADJUSTMENT','REVERSAL','TAX'] as $eventType)
                            <option value="{{ $eventType }}" @selected($selectedEventType === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_event_source') }}</label>
                    <select name="event_source" class="form-select">
                        <option value="">{{ trans('accounting::accounting.reports.treasury_statement.filter_all') }}</option>
                        @foreach(['manual','sales','inventory','system'] as $eventSource)
                            <option value="{{ $eventSource }}" @selected($selectedEventSource === $eventSource)>{{ $eventSource }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-funnel me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.apply') }}
                    </button>
                    @if($cashboxId > 0)
                        <a href="{{ route('admin.accounting.cashboxes.statement', ['cashbox' => $cashboxId]) }}" class="btn btn-outline-primary">
                            <i class="ph-eye me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.open_full_statement') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if(!empty($data['error']))
        <div class="alert alert-warning">{{ $data['error'] }}</div>
    @else
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $data['title'] }}</h5>
                @if(isset($statement['summary']))
                    <div class="small text-muted">
                        {{ trans('accounting::accounting.reports.treasury_statement.closing_balance') }}:
                        <strong>{{ number_format((float) ($statement['summary']['closing_balance'] ?? 0)) }}</strong>
                    </div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_date') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_document') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_event') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_description') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_debit') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_credit') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_running') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($statement['rows'] ?? []) as $row)
                            <tr>
                                <td>
                                    @if(!empty($row['posted_at']))
                                        {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                                    @endif
                                </td>
                                <td>{{ $row['document_number'] ?? '' }}</td>
                                <td>{{ $row['event_type'] ?? '' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($row['description'] ?? '', 100) }}</td>
                                <td class="text-end">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">{{ trans('accounting::accounting.reports.treasury_statement.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($statement['paginator']) && $statement['paginator'] instanceof \Illuminate\Pagination\LengthAwarePaginator && $statement['paginator']->hasPages())
                <div class="card-footer">
                    {{ $statement['paginator']->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
