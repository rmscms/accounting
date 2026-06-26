<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] ?? '' }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; margin-bottom: 6px; }
        .meta { font-size: 9pt; margin-bottom: 10px; color: #444; }
        .kpi { margin-bottom: 10px; }
        .kpi span { margin-left: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th { background: #f0f0f0; }
        .num { text-align: left; direction: ltr; }
    </style>
</head>
<body>
    <h1>{{ $data['title'] ?? '' }}</h1>
    @if(!empty($data['period']))
        <div class="meta">
            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['start']), 'Y/m/d') }}
            —
            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['end']), 'Y/m/d') }}
        </div>
    @endif
    @php($totals = (array) ($data['totals'] ?? []))
    <div class="kpi">
        <span>{{ trans('accounting::accounting.reports.insurance_monthly.kpi.opening_balance') }}: <strong>{{ number_format((float) ($totals['opening_balance'] ?? 0)) }}</strong></span>
        <span>{{ trans('accounting::accounting.reports.insurance_monthly.kpi.accrual_total') }}: <strong>{{ number_format((float) ($totals['accrual_total'] ?? 0)) }}</strong></span>
        <span>{{ trans('accounting::accounting.reports.insurance_monthly.kpi.payment_total') }}: <strong>{{ number_format((float) ($totals['payment_total'] ?? 0)) }}</strong></span>
        <span>{{ trans('accounting::accounting.reports.insurance_monthly.kpi.closing_balance') }}: <strong>{{ number_format((float) ($totals['closing_balance'] ?? 0)) }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.posted_at') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.source') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.reference') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.document_number') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.description') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.accrual_total') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.payment_total') }}</th>
                <th>{{ trans('accounting::accounting.reports.insurance_monthly.columns.net_change') }}</th>
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
                    <td class="num">{{ number_format((float) $row['accrual_total']) }}</td>
                    <td class="num">{{ number_format((float) $row['payment_total']) }}</td>
                    <td class="num">{{ number_format((float) $row['net_change']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">{{ trans('accounting::accounting.reports.insurance_monthly.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
