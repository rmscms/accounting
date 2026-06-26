@extends('cms::admin.layout.index')
@section('title', 'صورت سود و زیان')
@section('content')
@php
    $periodStart = isset($data['period']['start']) ? (string) $data['period']['start'] : null;
    $periodEnd = isset($data['period']['end']) ? (string) $data['period']['end'] : null;
    $incomeFromVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeFromDisplay(
        request('from_date'),
        request('start_date'),
        $periodStart
    );
    $incomeToVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeToDisplay(
        request('to_date'),
        request('end_date'),
        $periodEnd
    );
    $incomeCompareFromVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeFromDisplay(
        request('compare_from_date'),
        null,
        null
    );
    $incomeCompareToVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeToDisplay(
        request('compare_to_date'),
        null,
        null
    );
@endphp
<div class="container-fluid">
    <!-- فیلترها -->
    <div class="card mb-3">
        <div class="card-body">
            <form id="accounting-reports-filter-form" method="GET" class="row g-3 align-items-end">
                <x-accounting::date-range-filter
                    :from-value="$incomeFromVal"
                    :to-value="$incomeToVal"
                    from-col-class="col-md-3"
                    to-col-class="col-md-3"
                />
                <x-accounting::date-range-filter
                    from-name="compare_from_date"
                    to-name="compare_to_date"
                    :from-value="$incomeCompareFromVal"
                    :to-value="$incomeCompareToVal"
                    from-label="از تاریخ مقایسه"
                    to-label="تا تاریخ مقایسه"
                    from-col-class="col-md-3"
                    to-col-class="col-md-3"
                />
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-funnel me-1"></i> نمایش
                    </button>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="ph-printer me-1"></i> چاپ
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- صورت سود و زیان -->
    <div class="card">
        <div class="card-header text-center">
            <h3 class="mb-0">صورت سود و زیان (Income Statement)</h3>
            <p class="text-muted mb-0">دوره: {{ $data['period']['start'] }} تا {{ $data['period']['end'] }}</p>
        </div>
        <div class="card-body">
            @if(isset($data['comparison']) && is_array($data['comparison']))
                @php
                    $cmp = $data['comparison'];
                    $rows = [
                        ['جمع درآمد', (float) ($data['revenue']['total'] ?? 0), (float) ($cmp['revenue_total'] ?? 0)],
                        ['بهای تمام‌شده', (float) ($data['cost_of_goods_sold'] ?? 0), (float) ($cmp['cost_of_goods_sold'] ?? 0)],
                        ['سود ناخالص', (float) ($data['gross_profit'] ?? 0), (float) ($cmp['gross_profit'] ?? 0)],
                        ['هزینه‌های عملیاتی', (float) ($data['operating_expenses']['total'] ?? 0), (float) ($cmp['operating_expenses_total'] ?? 0)],
                        ['سود عملیاتی', (float) ($data['operating_income'] ?? 0), (float) ($cmp['operating_income'] ?? 0)],
                        ['سود قبل از مالیات', (float) ($data['income_before_tax'] ?? 0), (float) ($cmp['income_before_tax'] ?? 0)],
                        ['هزینه مالیات', (float) ($data['income_tax_expense'] ?? 0), (float) ($cmp['income_tax_expense'] ?? 0)],
                        ['سود خالص', (float) ($data['net_income'] ?? 0), (float) ($cmp['net_income'] ?? 0)],
                    ];
                @endphp
                <div class="alert alert-info mb-3">
                    <div class="fw-semibold mb-2">
                        مقایسه دوره فعلی با دوره
                        {{ $cmp['period']['start'] ?? '-' }} تا {{ $cmp['period']['end'] ?? '-' }}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>شاخص</th>
                                    <th class="text-end">فعلی</th>
                                    <th class="text-end">مقایسه</th>
                                    <th class="text-end">اختلاف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $metricRow)
                                    <tr>
                                        <td>{{ $metricRow[0] }}</td>
                                        <td class="text-end">{{ number_format($metricRow[1]) }}</td>
                                        <td class="text-end">{{ number_format($metricRow[2]) }}</td>
                                        <td class="text-end">{{ number_format($metricRow[1] - $metricRow[2]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <table class="table">
                <tbody>
                    <!-- درآمد فروش -->
                    <tr class="table-light">
                        <td colspan="2"><strong>درآمد فروش</strong></td>
                    </tr>
                    @foreach($data['revenue']['items'] as $item)
                    <tr>
                        <td class="ps-4">{{ $item['name'] }}</td>
                        <td class="text-end">{{ number_format($item['balance']) }}</td>
                    </tr>
                    @endforeach
                    <tr class="fw-bold">
                        <td>جمع درآمد فروش</td>
                        <td class="text-end text-success">{{ number_format($data['revenue']['total']) }}</td>
                    </tr>
                    
                    <!-- بهای تمام شده کالای فروش رفته -->
                    <tr class="table-light">
                        <td>بهای تمام شده کالای فروش رفته (COGS)</td>
                        <td class="text-end text-danger">{{ number_format($data['cost_of_goods_sold']) }}</td>
                    </tr>
                    
                    <!-- سود ناخالص -->
                    <tr class="table-success fw-bold">
                        <td><i class="ph-arrow-right me-1"></i> سود ناخالص</td>
                        <td class="text-end">
                            {{ number_format($data['gross_profit']) }}
                            <span class="badge bg-success ms-2">{{ number_format($data['gross_margin'], 1) }}%</span>
                        </td>
                    </tr>
                    
                    <!-- هزینه‌های عملیاتی -->
                    <tr class="table-light">
                        <td colspan="2"><strong>هزینه‌های عملیاتی</strong></td>
                    </tr>
                    <tr>
                        <td class="ps-4">جمع هزینه‌های عملیاتی</td>
                        <td class="text-end text-danger">{{ number_format($data['operating_expenses']['total']) }}</td>
                    </tr>
                    
                    <!-- سود عملیاتی -->
                    <tr class="table-info fw-bold">
                        <td><i class="ph-arrow-right me-1"></i> سود عملیاتی</td>
                        <td class="text-end">{{ number_format($data['operating_income']) }}</td>
                    </tr>
                    
                    <!-- سود قبل از مالیات -->
                    <tr class="table-warning fw-bold">
                        <td><i class="ph-arrow-right me-1"></i> سود قبل از مالیات (EBT)</td>
                        <td class="text-end">{{ number_format($data['income_before_tax']) }}</td>
                    </tr>
                    
                    @if(isset($data['income_tax_expense']) && $data['income_tax_expense'] > 0)
                    <!-- مالیات بر درآمد -->
                    <tr class="table-light">
                        <td class="ps-4">
                            هزینه مالیات بر درآمد 
                            <span class="badge bg-secondary">{{ number_format($data['income_tax_rate'], 1) }}%</span>
                        </td>
                        <td class="text-end text-danger">{{ number_format($data['income_tax_expense']) }}</td>
                    </tr>
                    @endif
                    
                    <!-- سود خالص (پس از مالیات) -->
                    <tr class="table-primary fw-bold fs-5">
                        <td><i class="ph-check-circle me-1"></i> سود (زیان) خالص (پس از مالیات)</td>
                        <td class="text-end {{ $data['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($data['net_income']) }}
                            <span class="badge {{ $data['net_income'] >= 0 ? 'bg-success' : 'bg-danger' }} ms-2">
                                {{ number_format($data['net_margin'], 1) }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <a href="{{ route('admin.accounting.reports.index') }}" class="btn btn-secondary">
                <i class="ph-arrow-left me-1"></i>
                بازگشت
            </a>
        </div>
    </div>
</div>
@endsection
