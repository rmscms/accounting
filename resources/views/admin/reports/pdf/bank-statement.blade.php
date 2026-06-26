<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] ?? '' }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; margin-bottom: 4px; }
        .meta { font-size: 9pt; color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th { background: #f0f0f0; }
        .num { text-align: left; direction: ltr; }
    </style>
</head>
<body>
    <h1>{{ $data['title'] ?? '' }}</h1>
    @if(!empty($data['bank']))
        <div class="meta">{{ $data['bank']->name }} @if($data['bank']->account_number) — {{ $data['bank']->account_number }} @endif</div>
    @endif
    @if(!empty($data['period']))
        <div class="meta">
            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['start']), 'Y/m/d') }}
            —
            {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['end']), 'Y/m/d') }}
        </div>
    @endif
    @if(isset($data['opening_balance']))
        <div class="meta">
            {{ trans('accounting::accounting.reports.bank_statement.opening_balance') }}: {{ number_format((float) $data['opening_balance']) }}
            —
            {{ trans('accounting::accounting.reports.bank_statement.closing_balance') }}: {{ number_format((float) ($data['closing_balance'] ?? 0)) }}
        </div>
    @endif

    @include('accounting::admin.reports.pdf.bank-statement-inner', ['data' => $data])
</body>
</html>
