@extends('cms::admin.layout.index')
@section('title', $data['title'] ?? 'گزارش')
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
    $mode = (string) ($data['mode'] ?? request('mode', 'detail'));
    if (!in_array($mode, ['detail', 'summary'], true)) {
        $mode = 'detail';
    }
    $bankId = (int) ($data['bank_id'] ?? request('bank_id', 0));
    $exportQuery = array_filter([
        'from_date' => $reportFromVal,
        'to_date' => $reportToVal,
        'bank_id' => $bankId > 0 ? $bankId : null,
        'mode' => $mode,
    ], fn ($v) => $v !== null && $v !== '');
    $exportQueryStr = http_build_query($exportQuery);
@endphp
<div class="container-fluid" data-role="bank-statement-report">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end" action="{{ route('admin.accounting.reports.bank-transactions') }}">
                <div class="col-md-3">
                    <label class="form-label">{{ trans('accounting::accounting.reports.bank_statement.filter_bank') }}</label>
                    <select name="bank_id" class="form-select" required>
                        <option value="">{{ trans('accounting::accounting.reports.bank_statement.select_placeholder') }}</option>
                        @foreach($data['banks'] ?? [] as $b)
                            <option value="{{ $b->id }}" @selected($bankId === (int) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ trans('accounting::accounting.reports.bank_statement.filter_mode') }}</label>
                    <select name="mode" class="form-select">
                        <option value="detail" @selected($mode === 'detail')>{{ trans('accounting::accounting.reports.bank_statement.mode_detail') }}</option>
                        <option value="summary" @selected($mode === 'summary')>{{ trans('accounting::accounting.reports.bank_statement.mode_summary') }}</option>
                    </select>
                </div>
                <x-accounting::date-range-filter
                    :from-value="$reportFromVal"
                    :to-value="$reportToVal"
                    from-col-class="col-md-2"
                    to-col-class="col-md-2"
                />
                <div class="col-md-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-funnel me-1"></i>{{ trans('accounting::accounting.reports.bank_statement.apply') }}
                    </button>
                    @if($bankId > 0 && empty($data['error']))
                        <a href="{{ route('admin.accounting.reports.bank-transactions.export.pdf', [], false) }}?{{ $exportQueryStr }}" class="btn btn-outline-danger">
                            <i class="ph-file-pdf me-1"></i>PDF
                        </a>
                        <a href="{{ route('admin.accounting.reports.bank-transactions.export.excel', [], false) }}?{{ $exportQueryStr }}" class="btn btn-outline-success">
                            <i class="ph-microsoft-excel-logo me-1"></i>Excel
                        </a>
                    @endif
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="ph-printer me-1"></i>{{ trans('accounting::accounting.reports.bank_statement.print') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(isset($data['error']))
        <div class="alert alert-warning">
            <i class="ph-warning me-2"></i>{{ $data['error'] }}
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-0">{{ $data['title'] }}</h4>
                    @if(isset($data['bank']))
                        <p class="text-muted mb-0 small">{{ $data['bank']->name }} @if($data['bank']->account_number) — {{ $data['bank']->account_number }} @endif</p>
                    @endif
                    @if(isset($data['period']))
                        <p class="text-muted mb-0 small">
                            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['start']), 'Y/m/d') }}
                            —
                            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['end']), 'Y/m/d') }}
                        </p>
                    @endif
                </div>
                @if(isset($data['opening_balance']))
                    <div class="text-end small">
                        <div>{{ trans('accounting::accounting.reports.bank_statement.opening_balance') }}: <strong>{{ number_format((float) $data['opening_balance']) }}</strong></div>
                        <div>{{ trans('accounting::accounting.reports.bank_statement.closing_balance') }}: <strong>{{ number_format((float) ($data['closing_balance'] ?? 0)) }}</strong></div>
                    </div>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted small">{{ trans('accounting::accounting.reports.bank_statement.posted_only_hint') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                @foreach($data['columns'] ?? [] as $col)
                                    <th>{{ $col }}</th>
                                @endforeach
                                @if(($data['mode'] ?? '') === 'summary')
                                    <th class="text-center" style="width: 100px;">{{ trans('accounting::accounting.reports.bank_statement.col_actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @if(($data['mode'] ?? '') === 'summary')
                                @foreach($data['summary_rows'] ?? [] as $row)
                                    <tr>
                                        <td>
                                            @if(!empty($row['posted_at']))
                                                {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                                            @endif
                                        </td>
                                        <td>{{ $row['document_number'] ?? '' }}</td>
                                        <td>{{ $row['document_type'] ?? '' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($row['description'] ?? '', 80) }}</td>
                                        <td class="text-end">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary bank-doc-expand" data-document-id="{{ (int) ($row['accounting_document_id'] ?? 0) }}">
                                                {{ trans('accounting::accounting.reports.bank_statement.show_lines') }}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="d-none bank-doc-detail-row" data-parent-document="{{ (int) ($row['accounting_document_id'] ?? 0) }}">
                                        <td colspan="8" class="bg-light small bank-doc-detail-cell"></td>
                                    </tr>
                                @endforeach
                            @else
                                @foreach($data['detail_rows'] ?? [] as $row)
                                    <tr>
                                        <td>
                                            @if(!empty($row['posted_at']))
                                                {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                                            @endif
                                        </td>
                                        <td>{{ $row['document_number'] ?? '' }}</td>
                                        <td>{{ $row['document_type'] ?? '' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($row['description'] ?? '', 80) }}</td>
                                        <td class="text-end">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                @if($bankId > 0 && (($data['mode'] ?? '') === 'summary' && count($data['summary_rows'] ?? []) === 0) || (($data['mode'] ?? '') === 'detail' && count($data['detail_rows'] ?? []) === 0))
                    <div class="alert alert-light border mt-3 mb-0">{{ trans('accounting::accounting.reports.bank_statement.empty') }}</div>
                @endif
            </div>
            <div class="card-footer">
                <a href="{{ route('admin.accounting.reports.index') }}" class="btn btn-secondary">
                    <i class="ph-arrow-left me-1"></i>{{ trans('accounting::accounting.reports.bank_statement.back') }}
                </a>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var docLinesUrlTpl = @json(str_replace('999999999', '__DOC__', route('admin.accounting.reports.bank-transactions.document.lines', ['document' => 999999999])));
    document.querySelectorAll('.bank-doc-expand').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-document-id');
            if (!id) return;
            var row = document.querySelector('tr.bank-doc-detail-row[data-parent-document="' + id + '"]');
            if (!row) return;
            var cell = row.querySelector('.bank-doc-detail-cell');
            if (row.classList.contains('d-none')) {
                row.classList.remove('d-none');
                if (cell && cell.getAttribute('data-loaded') === '1') {
                    return;
                }
                cell.innerHTML = '<span class="text-muted">...</span>';
                var fetchUrl = docLinesUrlTpl.replace('__DOC__', id);
                fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok || !data.lines) {
                            cell.innerHTML = '<span class="text-danger">—</span>';
                            return;
                        }
                        var html = '<table class="table table-bordered table-sm mb-0 bg-white"><thead><tr><th>کد</th><th>حساب</th><th class="text-end">بدهکار</th><th class="text-end">بستانکار</th><th>شرح</th></tr></thead><tbody>';
                        data.lines.forEach(function (l) {
                            html += '<tr><td>' + (l.account_code || '') + '</td><td>' + (l.account_name || '') + '</td><td class="text-end">' + (l.debit_amount || 0) + '</td><td class="text-end">' + (l.credit_amount || 0) + '</td><td>' + (l.description || '') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        cell.innerHTML = html;
                        cell.setAttribute('data-loaded', '1');
                    })
                    .catch(function () {
                        cell.innerHTML = '<span class="text-danger">خطا</span>';
                    });
            } else {
                row.classList.add('d-none');
            }
        });
    });
})();
</script>
@endpush
