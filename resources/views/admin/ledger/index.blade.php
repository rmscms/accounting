@extends('cms::admin.layout.index')

@section('title', 'دفتر روزنامه')

@section('content')
@php
    $ledgerFromVal = \RMS\Accounting\Support\AccountingDateUi::rangeInputFromRequest(request('from_date'), null);
    $ledgerToVal = \RMS\Accounting\Support\AccountingDateUi::rangeInputFromRequest(request('to_date'), null);
@endphp
<div class="page-header">
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                دفتر روزنامه <span class="fw-normal text-muted ms-2">ثبت خطوط به ترتیب زمان</span>
            </h4>
        </div>
    </div>
</div>

<div class="content">
    <div class="alert alert-info mb-3" role="alert">
        <div class="fw-semibold mb-2">تمایز با دفتر کل و ترازنامه</div>
        <p class="small mb-0">
            این صفحه فهرست ثبت‌های سیستم را به ترتیب تاریخ نشان می‌دهد (مفهوم نزدیک به دفتر روزنامه).
            <strong>گزارش دفتر کل</strong> به تفکیک حساب و جمع بدهکار/بستانکار/مانده در بخش گزارش‌هاست؛
            <strong>ترازنامه</strong> گزارش وضعیت مالی در یک تاریخ است و با دفتر کل متفاوت است.
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="{{ route('admin.accounting.reports.general-ledger') }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-book me-1"></i> گزارش دفتر کل
            </a>
            <a href="{{ route('admin.accounting.reports.subsidiary-ledger') }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-notebook me-1"></i> گزارش دفتر معین
            </a>
            <a href="{{ route('admin.accounting.reports.balance-sheet') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ph-scale me-1"></i> ترازنامه
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">ثبت‌های دفتر روزنامه</h5>
        </div>

        <div class="card-body">
            <!-- فیلترها -->
            <div class="ledger-filters row mb-3">
                <x-accounting::date-range-filter
                    :from-value="$ledgerFromVal"
                    :to-value="$ledgerToVal"
                    from-col-class="col-md-3"
                    to-col-class="col-md-3"
                />
                <div class="col-md-3 mb-2">
                    <label class="form-label">حساب</label>
                    <select class="form-select" name="account_id">
                        <option value="">همه حساب‌ها</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->code }} - {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary btn-filter-ledger me-2">
                        <i class="ph-funnel me-1"></i>
                        فیلتر
                    </button>
                    <button type="button" class="btn btn-light btn-reset-filter">
                        <i class="ph-arrow-counter-clockwise me-1"></i>
                        ریست
                    </button>
                </div>
            </div>

            <!-- جدول -->
            <div class="table-responsive">
                <table class="table table-bordered ledger-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">شناسه</th>
                            <th style="width: 150px;">تاریخ</th>
                            <th>حساب</th>
                            <th>شرح</th>
                            <th style="width: 150px;">بدهکار</th>
                            <th style="width: 150px;">بستانکار</th>
                            <th style="width: 120px;">سند</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                        <tr>
                            <td class="text-center">{{ $entry->id }}</td>
                            <td class="text-center">{{ $entry->created_at ? jdate($entry->created_at)->format('Y-m-d H:i') : '-' }}</td>
                            <td>
                                @if($entry->account)
                                    {{ $entry->account->code ?? '-' }} - {{ $entry->account->name ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $entry->description ?? '-' }}</td>
                            <td class="text-end ledger-debit">{{ $entry->debit_amount > 0 ? number_format($entry->debit_amount) : '-' }}</td>
                            <td class="text-end ledger-credit">{{ $entry->credit_amount > 0 ? number_format($entry->credit_amount) : '-' }}</td>
                            <td class="text-center">{{ $entry->document->document_number ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">هیچ ثبتی یافت نشد</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">جمع کل:</td>
                            <td class="text-end fw-bold ledger-debit">{{ number_format($totals['debit']) }}</td>
                            <td class="text-end fw-bold ledger-credit">{{ number_format($totals['credit']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
