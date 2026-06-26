@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.reports.treasury_statement.bank_title', ['name' => $bank->name]))
@section('content')
@php
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
    $selectedEventType = (string) request('event_type', ($statement['filters']['event_type'] ?? ''));
    $selectedEventSource = (string) request('event_source', ($statement['filters']['event_source'] ?? ''));
    $selectedDocSource = (string) request('doc_source', ($statement['filters']['doc_source'] ?? ''));
    $perPage = (int) request('per_page', ($statement['filters']['per_page'] ?? 25));
@endphp
<div class="container-fluid" data-role="bank-statement-show">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-1">{{ trans('accounting::accounting.reports.treasury_statement.bank_title', ['name' => $bank->name]) }}</h4>
                    <p class="text-muted mb-0 small">
                        {{ trans('accounting::accounting.reports.treasury_statement.account_label') }}:
                        <strong>{{ $bank->account?->code ?? '-' }}</strong>
                        @if(!empty($bank->account?->name))
                            — {{ $bank->account->name }}
                        @endif
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.banks.index') }}" class="btn btn-light">
                        <i class="ph-arrow-left me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.back_to_list') }}
                    </a>
                    <a href="{{ route('admin.accounting.banks.edit', ['bank' => $bank->id]) }}" class="btn btn-outline-primary">
                        <i class="ph-pencil-simple-line me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.edit_endpoint') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
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
                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_doc_source') }}</label>
                    <select name="doc_source" class="form-select">
                        <option value="">{{ trans('accounting::accounting.reports.treasury_statement.filter_all') }}</option>
                        @foreach(['manual','event','system'] as $docSource)
                            <option value="{{ $docSource }}" @selected($selectedDocSource === $docSource)>{{ $docSource }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_per_page') }}</label>
                    <select name="per_page" class="form-select">
                        @foreach([25,50,100,200] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.reports.treasury_statement.filter_search') }}</label>
                    <input type="text" name="q" value="{{ request('q', $statement['filters']['q'] ?? '') }}" class="form-control" placeholder="{{ trans('accounting::accounting.reports.treasury_statement.filter_search_placeholder') }}">
                </div>
                <div class="col-md-8 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-funnel me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.apply') }}
                    </button>
                    <a href="{{ route('admin.accounting.banks.show', ['bank' => $bank->id]) }}" class="btn btn-outline-secondary">
                        <i class="ph-arrow-counter-clockwise me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if(!empty($statement['error']))
        <div class="alert alert-warning">
            <i class="ph-warning me-1"></i>{{ $statement['error'] }}
        </div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.treasury_statement.opening_balance') }}</div>
                        <div class="h5 mb-0">{{ number_format((float) ($statement['summary']['opening_balance'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-success">
                    <div class="card-body">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.treasury_statement.total_inflow') }}</div>
                        <div class="h5 mb-0 text-success">{{ number_format((float) ($statement['summary']['total_inflow'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-danger">
                    <div class="card-body">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.treasury_statement.total_outflow') }}</div>
                        <div class="h5 mb-0 text-danger">{{ number_format((float) ($statement['summary']['total_outflow'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-indigo">
                    <div class="card-body">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.treasury_statement.closing_balance') }}</div>
                        <div class="h5 mb-0">{{ number_format((float) ($statement['summary']['closing_balance'] ?? 0)) }}</div>
                        <div class="small text-muted mt-1">{{ trans('accounting::accounting.reports.treasury_statement.transaction_count') }}: {{ number_format((int) ($statement['summary']['transaction_count'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="mb-2">{{ trans('accounting::accounting.reports.treasury_statement.diagnostics_title') }}</h6>
                    <div class="small">{{ trans('accounting::accounting.reports.treasury_statement.ledger_posted_balance') }}: <strong>{{ number_format((float) ($statement['diagnostics']['ledger_posted_balance'] ?? 0)) }}</strong></div>
                    <div class="small">{{ trans('accounting::accounting.reports.treasury_statement.stored_balance') }}: <strong>{{ number_format((float) ($statement['diagnostics']['stored_balance'] ?? 0)) }}</strong></div>
                    <div class="small">
                        {{ trans('accounting::accounting.reports.treasury_statement.difference') }}:
                        <strong class="{{ ((float) ($statement['diagnostics']['difference'] ?? 0)) === 0.0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format((float) ($statement['diagnostics']['difference'] ?? 0)) }}
                        </strong>
                    </div>
                </div>
                <div>
                    <form method="POST" action="{{ route('admin.accounting.treasury-sync.sync-one', ['type' => 'bank', 'id' => $bank->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="ph-arrows-clockwise me-1"></i>{{ trans('accounting::accounting.reports.treasury_statement.sync_now') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_date') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_document') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_event') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_source') }}</th>
                            <th>{{ trans('accounting::accounting.reports.treasury_statement.col_description') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_debit') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_credit') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.reports.treasury_statement.col_running') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statement['rows'] ?? [] as $row)
                            <tr>
                                <td>
                                    @if(!empty($row['posted_at']))
                                        {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                                    @endif
                                </td>
                                <td>{{ $row['document_number'] ?? '' }}</td>
                                <td>{{ $row['event_type'] ?? '' }}</td>
                                <td>{{ $row['event_source'] ?? '' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($row['description'] ?? '', 120) }}</td>
                                <td class="text-end">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ trans('accounting::accounting.reports.treasury_statement.empty') }}</td>
                            </tr>
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
