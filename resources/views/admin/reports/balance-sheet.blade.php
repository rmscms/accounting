@extends('cms::admin.layout.index')
@section('title', 'ترازنامه')
@section('content')
@php
    $asOfDisplay = \RMS\Accounting\Support\AccountingDateUi::rangeInputFromRequest(
        request('as_of_date'),
        \Carbon\Carbon::now()->format('Y-m-d')
    );
    $compareAsOfDisplay = \RMS\Accounting\Support\AccountingDateUi::rangeInputFromRequest(
        request('compare_as_of_date'),
        null
    );
@endphp
<div class="container-fluid">
    <!-- فیلترها -->
    <div class="card mb-3">
        <div class="card-body">
            <form id="accounting-reports-filter-form" method="GET" class="row g-3 align-items-end">
                <x-accounting::date-field
                    name="as_of_date"
                    label="تا تاریخ"
                    :value="$asOfDisplay"
                    col-class="col-md-4"
                />
                <x-accounting::date-field
                    name="compare_as_of_date"
                    label="تاریخ مقایسه"
                    :value="$compareAsOfDisplay"
                    col-class="col-md-4"
                />
                <div class="col-md-4">
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
    
    <!-- ترازنامه -->
    <div class="card">
        <div class="card-header text-center">
            <h3 class="mb-0">ترازنامه (Balance Sheet)</h3>
            <p class="text-muted mb-0">تا تاریخ: {{ $data['as_of_date'] }}</p>
        </div>
        <div class="card-body">
            @if(isset($data['comparison']) && is_array($data['comparison']))
                @php
                    $cmp = $data['comparison'];
                    $assetDelta = (float) ($data['assets']['total'] ?? 0) - (float) ($cmp['assets_total'] ?? 0);
                    $liabilityDelta = (float) ($data['liabilities']['total'] ?? 0) - (float) ($cmp['liabilities_total'] ?? 0);
                    $equityDelta = (float) ($data['equity']['total'] ?? 0) - (float) ($cmp['equity_total'] ?? 0);
                    $retainedDelta = (float) ($data['equity']['retained_earnings'] ?? 0) - (float) ($cmp['retained_earnings'] ?? 0);
                @endphp
                <div class="alert alert-info mb-3">
                    <div class="fw-semibold mb-2">مقایسه با تاریخ {{ $cmp['as_of_date'] ?? '-' }}</div>
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
                                <tr>
                                    <td>دارایی‌ها</td>
                                    <td class="text-end">{{ number_format((float) ($data['assets']['total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((float) ($cmp['assets_total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format($assetDelta) }}</td>
                                </tr>
                                <tr>
                                    <td>بدهی‌ها</td>
                                    <td class="text-end">{{ number_format((float) ($data['liabilities']['total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((float) ($cmp['liabilities_total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format($liabilityDelta) }}</td>
                                </tr>
                                <tr>
                                    <td>حقوق صاحبان</td>
                                    <td class="text-end">{{ number_format((float) ($data['equity']['total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((float) ($cmp['equity_total'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format($equityDelta) }}</td>
                                </tr>
                                <tr>
                                    <td>سود(زیان) انباشته</td>
                                    <td class="text-end">{{ number_format((float) ($data['equity']['retained_earnings'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((float) ($cmp['retained_earnings'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format($retainedDelta) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="row">
                <!-- دارایی‌ها (سمت چپ) -->
                <div class="col-md-6">
                    <h5 class="text-primary mb-3">
                        <i class="ph-wallet me-1"></i>
                        دارایی‌ها (Assets)
                    </h5>
                    <table class="table table-sm">
                        <tbody>
                            @foreach($data['assets']['items'] as $asset)
                            <tr>
                                <td>{{ $asset['code'] }} - {{ $asset['name'] }}</td>
                                <td class="text-end">{{ number_format($asset['balance']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-primary fw-bold">
                                <td>جمع دارایی‌ها</td>
                                <td class="text-end">{{ number_format($data['assets']['total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- بدهی‌ها و حقوق صاحبان (سمت راست) -->
                <div class="col-md-6">
                    <h5 class="text-danger mb-3">
                        <i class="ph-credit-card me-1"></i>
                        بدهی‌ها (Liabilities)
                    </h5>
                    <table class="table table-sm">
                        <tbody>
                            @foreach($data['liabilities']['items'] as $liability)
                            <tr>
                                <td>{{ $liability['code'] }} - {{ $liability['name'] }}</td>
                                <td class="text-end">{{ number_format($liability['balance']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-danger fw-bold">
                                <td>جمع بدهی‌ها</td>
                                <td class="text-end">{{ number_format($data['liabilities']['total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <h5 class="text-success mb-3 mt-4">
                        <i class="ph-chart-line me-1"></i>
                        حقوق صاحبان (Equity)
                    </h5>
                    <table class="table table-sm">
                        <tbody>
                            @foreach($data['equity']['items'] as $equity)
                            <tr>
                                <td>{{ $equity['code'] }} - {{ $equity['name'] }}</td>
                                <td class="text-end">{{ number_format($equity['balance']) }}</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td>سود (زیان) انباشته</td>
                                <td class="text-end">{{ number_format($data['equity']['retained_earnings']) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-success fw-bold">
                                <td>جمع حقوق صاحبان</td>
                                <td class="text-end">{{ number_format($data['equity']['total']) }}</td>
                            </tr>
                            <tr class="table-warning fw-bold">
                                <td>جمع بدهی‌ها + حقوق</td>
                                <td class="text-end">{{ number_format($data['equation']['liabilities_plus_equity']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- بررسی تعادل -->
            <div class="alert {{ $data['equation']['is_balanced'] ? 'alert-success' : 'alert-danger' }} mt-3">
                <div class="d-flex justify-content-between">
                    <span><strong>معادله حسابداری:</strong></span>
                    <span>دارایی‌ها ({{ number_format($data['equation']['assets']) }}) 
                    = بدهی‌ها + حقوق ({{ number_format($data['equation']['liabilities_plus_equity']) }})</span>
                    <span>
                        @if($data['equation']['is_balanced'])
                        <i class="ph-check-circle text-success"></i> متعادل
                        @else
                        <i class="ph-x-circle text-danger"></i> نامتعادل
                        @endif
                    </span>
                </div>
            </div>
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
