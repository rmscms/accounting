@extends('cms::admin.layout.index')
@section('title', 'داشبورد حسابداری')
@section('content')
<div class="container-fluid">
    <!-- دوره مالی فعال -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card border-start border-primary border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">
                                <i class="ph-calendar-check me-1 text-primary"></i>
                                دوره مالی فعال: {{ $fiscalYear->title }}
                            </h5>
                            <p class="text-muted mb-0">
                                از {{ $fiscalYear->start_date }} تا {{ $fiscalYear->end_date }}
                            </p>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('admin.accounting.fiscal_years.index') }}" class="btn btn-outline-primary">
                                <i class="ph-gear me-1"></i>
                                مدیریت دوره‌های مالی
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $rs = $readinessSummary ?? null;
    @endphp
    @if(is_array($rs) && empty($rs['all_required_ok']))
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="alert alert-warning border border-warning mb-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-start gap-2">
                    <i class="ph-warning-circle flex-shrink-0 fs-4 text-warning-emphasis"></i>
                    <div>
                        <strong class="d-block">{{ trans('accounting::accounting.dashboard.readiness_banner_title') }}</strong>
                        <span class="small text-muted">{{ trans('accounting::accounting.dashboard.readiness_banner_body', ['ok' => (int) ($rs['required_ok'] ?? 0), 'total' => (int) ($rs['required_total'] ?? 0)]) }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.onboarding') }}" class="btn btn-sm btn-warning">
                        <i class="ph-path me-1"></i>{{ trans('accounting::accounting.onboarding.page_title') }}
                    </a>
                    <a href="{{ route('admin.accounting.guides.opening-balance') }}" class="btn btn-sm btn-outline-dark">
                        <i class="ph-book-open me-1"></i>{{ trans('accounting::accounting.onboarding.link_guide') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    @php
        $sw = $setupWarnings ?? [];
        $showChequeSetupWarning = !empty($sw['missing_receivable_clearing']) || !empty($sw['missing_payable_clearing']);
    @endphp
    @if($showChequeSetupWarning)
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="alert alert-warning border border-warning mb-0 shadow-sm" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="ph-warning-circle flex-shrink-0 fs-4 text-warning-emphasis"></i>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-2">{{ trans('accounting::accounting.dashboard.setup_warnings.title') }}</h5>
                        <p class="mb-2 text-body-secondary">{{ trans('accounting::accounting.dashboard.setup_warnings.lead') }}</p>
                        <ul class="mb-3">
                            @if(!empty($sw['missing_receivable_clearing']))
                                <li>{{ trans('accounting::accounting.dashboard.setup_warnings.item_receivable', ['code' => $sw['suggested_code_receivable'] ?: '—']) }}</li>
                            @endif
                            @if(!empty($sw['missing_payable_clearing']))
                                <li>{{ trans('accounting::accounting.dashboard.setup_warnings.item_payable', ['code' => $sw['suggested_code_payable'] ?: '—']) }}</li>
                            @endif
                        </ul>
                        <p class="fw-semibold mb-1">{{ trans('accounting::accounting.dashboard.setup_warnings.steps_title') }}</p>
                        <ol class="mb-3 small">
                            <li>{!! trans('accounting::accounting.dashboard.setup_warnings.step_1', ['link' => '<a href="'.e(route('admin.accounting.accounts.index')).'" class="alert-link">'.e(trans('accounting::accounting.dashboard.setup_warnings.accounts_link')).'</a>']) !!}</li>
                            <li>{{ trans('accounting::accounting.dashboard.setup_warnings.step_2') }}</li>
                            <li>{{ trans('accounting::accounting.dashboard.setup_warnings.step_3') }} <a href="{{ route('admin.accounting.cheques.index') }}" class="alert-link">{{ trans('accounting::accounting.dashboard.setup_warnings.cheques_link') }}</a></li>
                        </ol>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                            @if(config('accounting.allow_dashboard_cheque_clearing_setup', true))
                                <form method="post" action="{{ route('admin.accounting.dashboard.setup-cheque-clearing-accounts') }}" class="d-inline" onsubmit="return confirm(@json(trans('accounting::accounting.dashboard.setup_warnings.run_setup_confirm')));">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-dark">
                                        <i class="ph-magic-wand me-1"></i>
                                        {{ trans('accounting::accounting.dashboard.setup_warnings.run_setup_button') }}
                                    </button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert" aria-label="{{ trans('accounting::accounting.dashboard.setup_warnings.dismiss') }}">{{ trans('accounting::accounting.dashboard.setup_warnings.dismiss') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @php
        $cdw = $chequeDataWarnings ?? [];
        $hasChequeDataWarnings = (int)($cdw['issued_missing_chequebook'] ?? 0) > 0
            || (int)($cdw['incomplete_linked_cheques'] ?? 0) > 0
            || (int)($cdw['payments_missing_cheque_link'] ?? 0) > 0;
    @endphp
    @if($hasChequeDataWarnings)
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="alert alert-warning border border-warning mb-0 shadow-sm" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="ph-warning-circle flex-shrink-0 fs-4 text-warning-emphasis"></i>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-2">{{ trans('accounting::accounting.dashboard.cheque_data_warnings.title') }}</h5>
                        <ul class="mb-2">
                            @if((int)($cdw['issued_missing_chequebook'] ?? 0) > 0)
                                <li>{{ trans('accounting::accounting.dashboard.cheque_data_warnings.issued_missing_chequebook', ['count' => (int)$cdw['issued_missing_chequebook']]) }}</li>
                            @endif
                            @if((int)($cdw['incomplete_linked_cheques'] ?? 0) > 0)
                                <li>{{ trans('accounting::accounting.dashboard.cheque_data_warnings.incomplete_linked_cheques', ['count' => (int)$cdw['incomplete_linked_cheques']]) }}</li>
                            @endif
                            @if((int)($cdw['payments_missing_cheque_link'] ?? 0) > 0)
                                <li>{{ trans('accounting::accounting.dashboard.cheque_data_warnings.payments_missing_cheque_link', ['count' => (int)$cdw['payments_missing_cheque_link']]) }}</li>
                            @endif
                        </ul>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.accounting.cheques.index') }}" class="btn btn-sm btn-outline-warning">
                                {{ trans('accounting::accounting.dashboard.cheque_data_warnings.open_cheques_btn') }}
                            </a>
                            <a href="{{ route('admin.accounting.chequebooks.index') }}" class="btn btn-sm btn-outline-primary">
                                {{ trans('accounting::accounting.dashboard.cheque_data_warnings.open_chequebooks_btn') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- آمار کلی - ردیف اول -->
    <div class="row g-3 mb-3">
        <!-- موجودی صندوق -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">موجودی صندوق</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['cash_balance']) }}</h3>
                            <span class="text-muted">تومان</span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-wallet display-6 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- موجودی بانک -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">موجودی بانک</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['bank_balance']) }}</h3>
                            <span class="text-muted">تومان</span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-bank display-6 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- مطالبات -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-start border-warning border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">مطالبات</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['accounts_receivable']) }}</h3>
                            <span class="text-warning">
                                <i class="ph-arrow-down me-1"></i>
                                طلب از مشتریان
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-arrow-circle-down display-6 text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- بدهی‌ها -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-start border-danger border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">بدهی‌ها</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['accounts_payable']) }}</h3>
                            <span class="text-danger">
                                <i class="ph-arrow-up me-1"></i>
                                بدهی به تامین‌کنندگان
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-arrow-circle-up display-6 text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- آمار ماهانه - ردیف دوم -->
    <div class="row g-3 mb-3">
        <!-- فاکتورهای ماه جاری -->
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">فاکتورهای این ماه</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['monthly_invoices_count']) }}</h3>
                            <span class="text-success">
                                <i class="ph-receipt me-1"></i>
                                فاکتور صادر شده
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-file-text display-6 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- درآمد ماه جاری -->
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">درآمد این ماه</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['monthly_revenue']) }}</h3>
                            <span class="text-success">
                                <i class="ph-trend-up me-1"></i>
                                تومان
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-currency-circle-dollar display-6 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- هزینه‌های ماه جاری -->
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">هزینه‌های این ماه</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['monthly_expenses']) }}</h3>
                            <span class="text-danger">
                                <i class="ph-trend-down me-1"></i>
                                تومان
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-credit-card display-6 text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار درآمد و هزینه -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-chart-line me-1"></i>
                        نمودار درآمد و هزینه (12 ماه اخیر)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas 
                        id="revenueExpenseChart" 
                        height="80"
                        data-labels='@json($chartData['labels'])'
                        data-revenue='@json($chartData['revenue'])'
                        data-expenses='@json($chartData['expenses'])'
                    ></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- آخرین فاکتورها و هزینه‌ها -->
    <div class="row g-3 mb-3">
        <!-- آخرین فاکتورها -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ph-receipt me-1"></i>
                        آخرین فاکتورها
                    </h5>
                    <a href="{{ route('admin.accounting.customer-invoices.index') }}" class="btn btn-sm btn-outline-primary">
                        مشاهده همه
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>شماره</th>
                                    <th>مشتری</th>
                                    <th>مبلغ</th>
                                    <th>تاریخ</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentInvoices as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->customer->name ?? '-' }}</td>
                                    <td>{{ number_format($invoice->total_amount) }}</td>
                                    <td>{{ $invoice->invoice_date }}</td>
                                    <td>
                                        @if($invoice->status === 'paid')
                                            <span class="badge bg-success">پرداخت شده</span>
                                        @elseif($invoice->status === 'partial')
                                            <span class="badge bg-warning">پرداخت جزئی</span>
                                        @else
                                            <span class="badge bg-danger">پرداخت نشده</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">فاکتوری یافت نشد</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- آخرین هزینه‌ها -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ph-credit-card me-1"></i>
                        آخرین هزینه‌ها
                    </h5>
                    <a href="{{ route('admin.accounting.expenses.index') }}" class="btn btn-sm btn-outline-primary">
                        مشاهده همه
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>شماره</th>
                                    <th>شرح</th>
                                    <th>مبلغ</th>
                                    <th>تاریخ</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentExpenses as $expense)
                                <tr>
                                    <td>{{ $expense->expense_number }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($expense->description, 20) }}</td>
                                    <td>{{ number_format((float) ($expense->total_amount ?? $expense->amount)) }}</td>
                                    <td>
                                        @if($expense->expense_date && function_exists('\RMS\Helper\persian_date'))
                                            <span class="text-nowrap">{{ \RMS\Helper\persian_date($expense->expense_date, 'Y/m/d') }}</span>
                                        @else
                                            {{ $expense->expense_date }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($expense->status === 'approved')
                                            <span class="badge bg-success">تایید شده</span>
                                        @elseif($expense->status === 'paid')
                                            <span class="badge bg-primary">پرداخت شده</span>
                                        @elseif($expense->status === 'pending')
                                            <span class="badge bg-warning">در انتظار</span>
                                        @elseif($expense->status === 'draft')
                                            <span class="badge bg-secondary">پیش‌نویس</span>
                                        @elseif($expense->status === 'rejected')
                                            <span class="badge bg-danger">رد شده</span>
                                        @elseif($expense->status === 'cancelled')
                                            <span class="badge bg-dark">لغو شده</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $expense->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">هزینه‌ای یافت نشد</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- دسترسی سریع -->
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-plus-circle me-1 text-success"></i>
                        ثبت سریع
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.customer-invoices.create') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-plus me-1"></i>
                            فاکتور فروش
                        </a>
                        <a href="{{ route('admin.accounting.expenses.create') }}" class="btn btn-outline-danger btn-sm">
                            <i class="ph-plus me-1"></i>
                            ثبت هزینه
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-currency-circle-dollar me-1 text-primary"></i>
                        پرداخت‌ها
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.customer-payments.index') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-arrow-down me-1"></i>
                            دریافت از مشتری
                        </a>
                        <a href="{{ route('admin.accounting.supplier-payments.index') }}" class="btn btn-outline-danger btn-sm">
                            <i class="ph-arrow-up me-1"></i>
                            پرداخت به تامین‌کننده
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-book me-1 text-info"></i>
                        دفاتر
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.general-ledger') }}" class="btn btn-outline-info btn-sm">
                            <i class="ph-notebook me-1"></i>
                            دفتر کل
                        </a>
                        <a href="{{ route('admin.accounting.documents.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ph-files me-1"></i>
                            اسناد حسابداری
                        </a>
                        <a href="{{ route('admin.accounting.treasury-sync.index') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-arrows-clockwise me-1"></i>
                            سینک خزانه با دفتر
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-chart-bar me-1 text-success"></i>
                        گزارشات
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.balance-sheet') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-scale me-1"></i>
                            ترازنامه
                        </a>
                        <a href="{{ route('admin.accounting.reports.profit-loss') }}" class="btn btn-outline-warning btn-sm">
                            <i class="ph-chart-line me-1"></i>
                            سود و زیان
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @include('accounting::admin.partials.utility-bills-hub')
    </div>
</div>
@endsection
