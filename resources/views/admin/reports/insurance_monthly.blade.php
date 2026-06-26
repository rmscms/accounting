@extends('cms::admin.layout.index')
@section('title', $data['title'] ?? trans('accounting::accounting.reports.insurance_monthly.title'))
@section('content')
@php
    $periodStart = isset($data['period']['start']) ? (string) $data['period']['start'] : null;
    $periodEnd = isset($data['period']['end']) ? (string) $data['period']['end'] : null;
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
    $exportQuery = array_filter([
        'from_date' => $reportFromVal,
        'to_date' => $reportToVal,
    ], fn ($v) => $v !== null && $v !== '');
    $exportQueryStr = http_build_query($exportQuery);
    $totals = (array) ($data['totals'] ?? []);
    $isBalanced = (bool) ($totals['is_balanced'] ?? false);
@endphp
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.accounting.reports.insurance-monthly') }}" class="row g-3 align-items-end">
                <x-accounting::date-range-filter
                    :from-value="$reportFromVal"
                    :to-value="$reportToVal"
                    from-col-class="col-md-3"
                    to-col-class="col-md-3"
                />
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-funnel me-1"></i>
                        {{ trans('accounting::accounting.reports.insurance_monthly.actions.apply_filters') }}
                    </button>
                    <a href="{{ route('admin.accounting.reports.insurance-monthly.export.excel', [], false) }}?{{ $exportQueryStr }}" class="btn btn-outline-success">
                        <i class="ph-microsoft-excel-logo me-1"></i>
                        Excel
                    </a>
                    <a href="{{ route('admin.accounting.reports.insurance-monthly.export.pdf', [], false) }}?{{ $exportQueryStr }}" class="btn btn-outline-danger">
                        <i class="ph-file-pdf me-1"></i>
                        PDF
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="ph-printer me-1"></i>
                        {{ trans('accounting::accounting.reports.insurance_monthly.actions.print') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::admin.reports.partials.report-messages')

    @if(!empty($data['error']))
        <div class="alert alert-warning">{{ $data['error'] }}</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.opening_balance') }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($totals['opening_balance'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.accrual_employee') }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($totals['accrual_employee'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.accrual_employer') }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($totals['accrual_employer'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.payment_total') }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($totals['payment_total'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.closing_balance') }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($totals['closing_balance'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ trans('accounting::accounting.reports.insurance_monthly.kpi.balance_status') }}</div>
                        <div class="mt-1">
                            @if($isBalanced)
                                <span class="badge bg-success">{{ trans('accounting::accounting.reports.insurance_monthly.status.balanced') }}</span>
                            @else
                                <span class="badge bg-danger">{{ trans('accounting::accounting.reports.insurance_monthly.status.mismatch') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.title') }}</h5>
                <span class="badge {{ $isBalanced ? 'bg-success' : 'bg-danger' }}">
                    {{ $isBalanced ? trans('accounting::accounting.reports.insurance_monthly.status.balanced') : trans('accounting::accounting.reports.insurance_monthly.status.mismatch') }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.formula') }}</div>
                        <div class="fw-semibold">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.formula_text') }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.formula_difference') }}</div>
                        <div class="fw-semibold {{ abs((float) ($totals['formula_difference'] ?? 0)) < 0.01 ? 'text-success' : 'text-danger' }}">
                            {{ number_format((float) ($totals['formula_difference'] ?? 0)) }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.ledger_accrual_payment') }}</div>
                        <div class="fw-semibold">
                            {{ number_format((float) ($totals['ledger_accrual_total'] ?? 0)) }}
                            /
                            {{ number_format((float) ($totals['ledger_payment_total'] ?? 0)) }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">{{ trans('accounting::accounting.reports.insurance_monthly.reconciliation.ledger_difference') }}</div>
                        <div class="fw-semibold {{ abs((float) ($totals['ledger_difference'] ?? 0)) < 0.01 ? 'text-success' : 'text-danger' }}">
                            {{ number_format((float) ($totals['ledger_difference'] ?? 0)) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ trans('accounting::accounting.reports.insurance_monthly.detail_title') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.posted_at') }}</th>
                                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.source') }}</th>
                                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.reference') }}</th>
                                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.document_number') }}</th>
                                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.description') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.reports.insurance_monthly.columns.accrual_employee') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.reports.insurance_monthly.columns.accrual_employer') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.reports.insurance_monthly.columns.accrual_total') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.reports.insurance_monthly.columns.payment_total') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.reports.insurance_monthly.columns.net_change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($data['source_rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ \RMS\Helper\persian_date(\Carbon\Carbon::parse((string) $row['posted_at']), 'Y/m/d H:i') }}</td>
                                    <td>{{ $row['source_label'] }}</td>
                                    <td>{{ $row['reference'] }}</td>
                                    <td>{{ $row['document_number'] }}</td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="text-end">{{ number_format((float) $row['accrual_employee']) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['accrual_employer']) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $row['accrual_total']) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['payment_total']) }}</td>
                                    <td class="text-end {{ (float) $row['net_change'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format((float) $row['net_change']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">{{ trans('accounting::accounting.reports.insurance_monthly.empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('admin.accounting.reports.index') }}" class="btn btn-secondary">
                    <i class="ph-arrow-left me-1"></i>
                    {{ trans('accounting::accounting.reports.insurance_monthly.actions.back') }}
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
