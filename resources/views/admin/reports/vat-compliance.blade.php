@extends('cms::admin.layout.index')
@section('title', 'VAT Compliance')
@section('content')
@php
    $dateCal = \RMS\Accounting\Support\AccountingDateUi::calendarMode();
    $isJalali = $dateCal === \RMS\Accounting\Support\AccountingDateUi::MODE_JALALI;
    $dateInputClass = 'form-control accounting-date-field'.($isJalali ? ' persian-datepicker' : '');
@endphp
<div class="container-fluid">
    @include('accounting::admin.reports.partials.report-messages')

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small">VAT خروجی</div>
                    <h5>{{ number_format((float) data_get($data, 'output_vat.vat', 0)) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">VAT ورودی</div>
                    <h5>{{ number_format((float) data_get($data, 'input_vat.vat', 0)) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger h-100">
                <div class="card-body">
                    <div class="text-muted small">مانده قابل پرداخت</div>
                    <h5>{{ number_format((float) data_get($data, 'net_payable_remaining', 0)) }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">ثبت تسویه VAT</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.accounting.reports.vat-remittances.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">مبلغ</label>
                            <input type="text" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تاریخ پرداخت</label>
                            <input type="text" name="payment_date" class="{{ $dateInputClass }}" data-calendar="{{ $dateCal }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">شروع دوره</label>
                            <input type="text" name="period_start" class="{{ $dateInputClass }}" data-calendar="{{ $dateCal }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">پایان دوره</label>
                            <input type="text" name="period_end" class="{{ $dateInputClass }}" data-calendar="{{ $dateCal }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">بانک</label>
                            <select name="bank_id" class="form-select">
                                <option value="">انتخاب</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">صندوق</label>
                            <select name="cash_box_id" class="form-select">
                                <option value="">انتخاب</option>
                                @foreach($cashBoxes as $cashBox)
                                    <option value="{{ $cashBox->id }}">{{ $cashBox->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">کیف پول</label>
                            <select name="wallet_id" class="form-select">
                                <option value="">انتخاب</option>
                                @foreach($wallets as $wallet)
                                    <option value="{{ $wallet->id }}">{{ $wallet->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">توضیحات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">ثبت تسویه</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">ایجاد اظهارنامه فصلی / اصلاحیه</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.accounting.reports.vat-declarations.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">شروع دوره</label>
                            <input type="text" name="period_start" class="{{ $dateInputClass }}" data-calendar="{{ $dateCal }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">پایان دوره</label>
                            <input type="text" name="period_end" class="{{ $dateInputClass }}" data-calendar="{{ $dateCal }}" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">اصلاحیه برای اظهارنامه</label>
                            <select name="parent_declaration_id" class="form-select">
                                <option value="">بدون اصلاحیه (نسخه اصلی)</option>
                                @foreach($declarations as $declaration)
                                    <option value="{{ $declaration->id }}">
                                        #{{ $declaration->id }} - {{ $declaration->period_start?->format('Y-m-d') }} تا {{ $declaration->period_end?->format('Y-m-d') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">توضیحات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success" type="submit">ایجاد پیش‌نویس اظهارنامه</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">تاریخچه تسویه VAT</h5></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>تاریخ</th>
                                <th>مبلغ</th>
                                <th>سند</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($remittances as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td>{{ optional($item->payment_date)->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $item->amount) }}</td>
                                <td>{{ $item->accountingDocument->document_number ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">موردی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">اظهارنامه‌ها / فرم 169</h5></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>دوره</th>
                                <th>وضعیت</th>
                                <th>اقدام</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($declarations as $item)
                            <tr>
                                <td>#{{ $item->id }} (v{{ $item->version }})</td>
                                <td>{{ optional($item->period_start)->format('Y-m-d') }} تا {{ optional($item->period_end)->format('Y-m-d') }}</td>
                                <td>{{ $item->status }}</td>
                                <td class="d-flex gap-1">
                                    <form method="POST" action="{{ route('admin.accounting.reports.vat-declarations.submit', $item->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">ثبت نهایی</button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-success" href="{{ route('admin.accounting.reports.vat-declarations.export-official', $item->id) }}">
                                        خروجی 169
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">اظهارنامه‌ای وجود ندارد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
