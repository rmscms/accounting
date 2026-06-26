@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.equity_overview.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">{{ trans('accounting::accounting.equity_overview.title') }}</h4>
            <p class="text-muted small mb-0">{{ trans('accounting::accounting.equity_overview.lead') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.accounting.shareholders.index') }}" class="btn btn-outline-secondary btn-sm">{{ trans('accounting::accounting.shareholders.title') }}</a>
            <a href="{{ route('admin.accounting.shareholder-withdrawals.index') }}" class="btn btn-outline-secondary btn-sm">{{ trans('accounting::accounting.withdrawals.title') }}</a>
            <a href="{{ route('admin.accounting.shareholder-capital-contributions.index') }}" class="btn btn-outline-primary btn-sm">{{ trans('accounting::accounting.capital.title') }}</a>
        </div>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        {{ trans('accounting::accounting.equity_overview.method_note') }}
    </div>

    @foreach($sections as $section)
        @php
            $ccy = $section['currency'];
            $totalCapital = (float) $section['total_capital'];
            $totalWd = (float) $section['total_withdrawals'];
        @endphp
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ trans('accounting::accounting.equity_overview.section_currency', ['currency' => $ccy]) }}</strong>
                <span class="small text-muted">
                    {{ trans('accounting::accounting.equity_overview.totals_inline', [
                        'capital' => number_format($totalCapital, 2),
                        'withdrawals' => number_format($totalWd, 2),
                    ]) }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ trans('accounting::accounting.equity_overview.col_shareholder') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.equity_overview.col_capital') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.equity_overview.col_capital_pct') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.equity_overview.col_withdrawals') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.equity_overview.col_fair_withdrawal') }}</th>
                            <th class="text-end">{{ trans('accounting::accounting.equity_overview.col_diff') }}</th>
                            <th>{{ trans('accounting::accounting.equity_overview.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            @php
                                /** @var \RMS\Accounting\Models\Shareholder $sh */
                                $sh = $row['shareholder'];
                                $capital = (float) $row['capital'];
                                $wd = (float) $row['withdrawals'];
                                $pct = $row['capital_share_pct'];
                                $fair = $row['fair_withdrawal'];
                                $diff = $row['diff_vs_fair'];
                                $st = $row['status'];
                                $badge = match ($st) {
                                    'over' => 'danger',
                                    'under' => 'secondary',
                                    'no_capital_base' => 'warning',
                                    'no_split' => 'secondary',
                                    default => 'success',
                                };
                                $statusLabel = trans('accounting::accounting.equity_overview.status_'.$st);
                            @endphp
                            <tr>
                                <td>
                                    {{ $sh->name }}
                                    @if(!$sh->active)
                                        <span class="badge bg-secondary ms-1">{{ trans('accounting::accounting.common.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($capital, 2) }} {{ $ccy }}</td>
                                <td class="text-end">{{ $pct !== null ? number_format($pct, 2).' %' : '—' }}</td>
                                <td class="text-end">{{ number_format($wd, 2) }} {{ $ccy }}</td>
                                <td class="text-end">{{ $fair !== null ? number_format($fair, 2).' '.$ccy : '—' }}</td>
                                <td class="text-end">{{ $diff !== null ? number_format($diff, 2).' '.$ccy : '—' }}</td>
                                <td><span class="badge bg-{{ $badge }}">{{ $statusLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
