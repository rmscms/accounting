@extends('cms::admin.layout.index')

@section('title', (string) ($customer->name ?? trans('accounting::accounting.customer.name')))

@section('content')
@php
    $customer = $customer ?? null;
    $party = $party ?? null;
    $summary = is_array($summary ?? null) ? $summary : [];
    $statementRows = is_array($statementRows ?? null) ? $statementRows : [];
    $paymentChannelSummary = is_array($paymentChannelSummary ?? null) ? $paymentChannelSummary : [];
    $baseCurrency = (string) ($baseCurrency ?? 'IRR');
    $filters = is_array($filters ?? null) ? $filters : [];
    $routes = is_array($routes ?? null) ? $routes : [];

    $typeLabels = [
        'invoice' => 'فاکتور فروش',
        'payment' => 'دریافت',
        'payment_correction' => 'اصلاح دریافت',
        'credit_note' => 'کردیت‌نوت',
        'refund' => 'بازگشت وجه',
        'refund_correction' => 'اصلاح بازگشت وجه',
        'purchase_invoice' => 'فاکتور خرید',
        'supplier_payment' => 'پرداخت تامین‌کننده',
        'supplier_payment_correction' => 'اصلاح پرداخت تامین‌کننده',
    ];
@endphp

<div class="container-fluid" data-role="customer-detail-root">
    <div class="card border-primary border-opacity-25 mb-3">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $customer->name ?? 'مشتری' }}</h5>
                <small class="text-muted d-block">
                    شناسه مشتری: #{{ (int) ($customer->id ?? 0) }}
                    @if(!empty($party?->id))
                        — شناسه طرف حساب: #{{ (int) $party->id }}
                    @endif
                    @if(!empty($customer?->default_currency_code))
                        — ارز پیش‌فرض: {{ strtoupper((string) $customer->default_currency_code) }}
                    @endif
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(!empty($routes['index']))
                    <a href="{{ $routes['index'] }}" class="btn btn-light btn-sm">بازگشت</a>
                @endif
                @if(!empty($routes['edit']))
                    <a href="{{ $routes['edit'] }}" class="btn btn-outline-primary btn-sm">ویرایش مشتری</a>
                @endif
                @if(!empty($routes['pdf_standard']))
                    <a href="{{ $routes['pdf_standard'] }}" class="btn btn-outline-secondary btn-sm">PDF استاندارد</a>
                @endif
                @if(!empty($routes['excel_standard']))
                    <a href="{{ $routes['excel_standard'] }}" class="btn btn-outline-secondary btn-sm">Excel استاندارد</a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" class="form-control persian-datepicker"
                           data-role="customer-filter-start-date"
                           data-format="YYYY/MM/DD"
                           value="{{ (string) ($filters['start_date'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" class="form-control persian-datepicker"
                           data-role="customer-filter-end-date"
                           data-format="YYYY/MM/DD"
                           value="{{ (string) ($filters['end_date'] ?? '') }}">
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" data-action="open-statement-report-modal">گزارش گردش</button>
                    <button type="button" class="btn btn-success" data-action="open-statement-payment-modal">ثبت دریافت / پرداخت</button>
                    <button type="button" class="btn btn-outline-warning" data-action="toggle-correction-rows" data-showing-corrections="0">نمایش اصلاحات</button>
                    @if(!empty($routes['rebuild']))
                        <form method="post" action="{{ $routes['rebuild'] }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-dark">بازسازی مانده</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">مانده مشتری</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) ($summary['customer_balance'] ?? 0)) }} {{ $baseCurrency }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">مانده تامین‌کننده</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) ($summary['supplier_balance'] ?? 0)) }} {{ $baseCurrency }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">جمع بدهکار</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) ($summary['total_debit'] ?? 0)) }} {{ $baseCurrency }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">جمع بستانکار</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) ($summary['total_credit'] ?? 0)) }} {{ $baseCurrency }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">گردش دفتر مشتری</h6>
            <small class="text-muted">{{ count($statementRows) }} ردیف</small>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                <tr>
                    <th>تاریخ</th>
                    <th>نوع</th>
                    <th>مرجع</th>
                    <th>کانال</th>
                    <th>توضیحات</th>
                    <th class="text-end">بدهکار</th>
                    <th class="text-end">بستانکار</th>
                    <th class="text-end">مانده</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody data-role="ledger-statement-rows">
                @forelse($statementRows as $row)
                    @php
                        $meta = (array) ($row['meta'] ?? []);
                        $channel = (string) ($meta['payment_channel'] ?? $meta['payment_method_name'] ?? $meta['refund_method'] ?? '-');
                        $description = (string) ($meta['status_label'] ?? $meta['notes'] ?? '-');
                        $type = (string) ($row['type'] ?? '');
                        $paymentId = (int) ($meta['payment_id'] ?? 0);
                        $refundId = (int) ($meta['refund_id'] ?? 0);
                        $supplierPaymentId = (int) ($meta['supplier_payment_id'] ?? 0);
                        $canCorrect = $paymentId > 0 || $refundId > 0 || $supplierPaymentId > 0;
                        $kind = $paymentId > 0 ? 'payment' : ($refundId > 0 ? 'refund' : 'supplier_payment');
                        $sourceId = $paymentId > 0 ? $paymentId : ($refundId > 0 ? $refundId : $supplierPaymentId);
                    @endphp
                    <tr>
                        <td>{{ (string) ($row['date'] ?? '-') }}</td>
                        <td>{{ $typeLabels[$type] ?? $type }}</td>
                        <td>{{ (string) ($row['reference'] ?? '-') }}</td>
                        <td>{{ $channel }}</td>
                        <td>{{ $description }}</td>
                        <td class="text-end">{{ number_format((float) ($row['debit'] ?? 0), 4) }}</td>
                        <td class="text-end">{{ number_format((float) ($row['credit'] ?? 0), 4) }}</td>
                        <td class="text-end">{{ number_format((float) ($row['running_balance'] ?? 0), 4) }}</td>
                        <td>
                            @if($canCorrect)
                                <button type="button"
                                        class="btn btn-sm btn-outline-warning"
                                        data-action="open-payment-correction"
                                        data-correction-kind="{{ $kind }}"
                                        data-source-id="{{ $sourceId }}"
                                        data-reference="{{ (string) ($row['reference'] ?? '') }}"
                                        data-date="{{ (string) ($row['date'] ?? '') }}"
                                        data-amount="{{ $kind === 'payment' ? (float) ($row['credit'] ?? 0) : (float) ($row['debit'] ?? 0) }}"
                                        data-channel="{{ $channel }}"
                                        data-payment-method-id="{{ (int) ($meta['payment_method_id'] ?? 0) }}">
                                    اصلاح
                                </button>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">گردشی برای این مشتری یافت نشد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">تحلیل کانال پرداخت</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>کانال</th>
                            <th class="text-end">دریافت</th>
                            <th class="text-end">بازگشت</th>
                            <th class="text-end">خالص</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($paymentChannelSummary as $item)
                            <tr>
                                <td>{{ (string) ($item['channel'] ?? '-') }}</td>
                                <td class="text-end">{{ number_format((float) ($item['received_amount'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($item['refunded_amount'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($item['net_amount'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">داده‌ای ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            @include('cms::admin.components.document-attachments-box', [
                'attachable_type' => (string) data_get($attachments ?? [], 'attachable_type', \RMS\Accounting\Models\Customer::class),
                'attachable_id' => (int) data_get($attachments ?? [], 'attachable_id', $customer->id ?? 0),
                'document_types' => (array) data_get($attachments ?? [], 'document_types', []),
            ])
        </div>
    </div>
</div>

@include('admin.components.customer-statement-report-modal')
@include('admin.components.customer-statement-payment-modal', [
    'paymentMethods' => $paymentMethods ?? [],
    'cashBoxes' => $cashBoxes ?? [],
    'banks' => $banks ?? [],
    'posTerminals' => $posTerminals ?? [],
    'wallets' => $wallets ?? [],
])
@include('admin.components.customer-statement-payment-correction-modal', [
    'paymentMethods' => $paymentMethods ?? [],
    'cashBoxes' => $cashBoxes ?? [],
    'banks' => $banks ?? [],
    'posTerminals' => $posTerminals ?? [],
    'wallets' => $wallets ?? [],
])
@endsection
