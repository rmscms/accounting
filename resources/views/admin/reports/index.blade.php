@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.reports.hub.page_title'))
@section('content')
<div class="container-fluid">
    <!-- سربرگ -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h4 class="mb-1">
                                <i class="ph-file-text me-2 text-primary"></i>
                                {{ trans('accounting::accounting.reports.hub.page_title') }}
                            </h4>
                            <p class="text-muted mb-0">{{ trans('accounting::accounting.reports.hub.page_subtitle') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارش‌های مالی اصلی -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-chart-line me-1"></i>
                        گزارش‌های مالی اصلی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.general-ledger') }}" class="btn btn-outline-primary w-100 text-start">
                                <i class="ph-book me-2"></i>
                                دفتر کل
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.subsidiary-ledger') }}" class="btn btn-outline-primary w-100 text-start">
                                <i class="ph-notebook me-2"></i>
                                دفتر معین
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.trial-balance') }}" class="btn btn-outline-primary w-100 text-start">
                                <i class="ph-scales me-2"></i>
                                تراز آزمایشی
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.balance-sheet') }}" class="btn btn-outline-success w-100 text-start">
                                <i class="ph-scale me-2"></i>
                                ترازنامه
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.income-statement') }}" class="btn btn-outline-success w-100 text-start">
                                <i class="ph-chart-bar me-2"></i>
                                صورت سود و زیان
                            </a>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.accounting.reports.cash-flow') }}" class="btn btn-outline-success w-100 text-start">
                                <i class="ph-arrows-clockwise me-2"></i>
                                گردش وجوه نقد
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارش‌های پرسنلی -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-identification-badge me-1"></i>
                        گزارش‌های پرسنلی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.employee-contracts') }}" class="btn btn-outline-primary w-100 text-start">
                                <i class="ph-identification-card me-2"></i>
                                گزارش قرارداد کارمندان
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.employee-loan-balances') }}" class="btn btn-outline-info w-100 text-start">
                                <i class="ph-hand-coins me-2"></i>
                                گزارش مانده وام کارکنان
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.employee-loan-installments-due') }}" class="btn btn-outline-info w-100 text-start">
                                <i class="ph-calendar-check me-2"></i>
                                گزارش سررسید اقساط وام
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.attendance-monthly-summary') }}" class="btn btn-outline-primary w-100 text-start">
                                <i class="ph-calendar me-2"></i>
                                گزارش خلاصه کارکرد ماهانه
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.attendance-overtime-detail') }}" class="btn btn-outline-warning w-100 text-start">
                                <i class="ph-timer me-2"></i>
                                گزارش جزئیات اضافه‌کار
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.attendance-leave-absence') }}" class="btn btn-outline-secondary w-100 text-start">
                                <i class="ph-bed me-2"></i>
                                گزارش مرخصی و غیبت
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.attendance-termination-settlement') }}" class="btn btn-outline-danger w-100 text-start">
                                <i class="ph-user-minus me-2"></i>
                                گزارش تسویه پایان همکاری
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.attendance-payroll-reconciliation') }}" class="btn btn-outline-dark w-100 text-start">
                                <i class="ph-link-break me-2"></i>
                                تطبیق کارکرد با حقوق و دفتر کل
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('admin.accounting.reports.insurance-monthly') }}" class="btn btn-outline-success w-100 text-start">
                                <i class="ph-shield-checkered me-2"></i>
                                {{ trans('accounting::accounting.reports.insurance_monthly.title') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارش‌های دریافتنی -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-arrow-down-left text-success me-1"></i>
                        گزارش‌های دریافتنی (AR)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.accounts-receivable') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-currency-circle-dollar me-2"></i>
                            حساب‌های دریافتنی
                        </a>
                        <a href="{{ route('admin.accounting.reports.customer-balances') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-users me-2"></i>
                            مانده حساب مشتریان
                        </a>
                        <a href="{{ route('admin.accounting.reports.overdue-customers') }}" class="btn btn-outline-warning btn-sm text-start">
                            <i class="ph-warning me-2"></i>
                            مشتریان معوق
                        </a>
                        <a href="{{ route('admin.accounting.reports.aging-analysis-ar') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-clock me-2"></i>
                            تحلیل سنی دریافتنی‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.customer-statement') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-scroll me-2"></i>
                            صورتحساب مشتری
                        </a>
                        <a href="{{ route('admin.accounting.reports.customer-invoices-history') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-invoice me-2"></i>
                            تاریخچه فاکتورهای مشتری
                        </a>
                        <a href="{{ route('admin.accounting.reports.payments-received-history') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-hand-coins me-2"></i>
                            تاریخچه دریافت‌ها
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- گزارش‌های پرداختنی -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-arrow-up-right text-danger me-1"></i>
                        گزارش‌های پرداختنی (AP)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.accounts-payable') }}" class="btn btn-outline-danger btn-sm text-start">
                            <i class="ph-currency-circle-dollar me-2"></i>
                            حساب‌های پرداختنی
                        </a>
                        <a href="{{ route('admin.accounting.reports.supplier-balances') }}" class="btn btn-outline-danger btn-sm text-start">
                            <i class="ph-truck me-2"></i>
                            مانده حساب تامین‌کنندگان
                        </a>
                        <a href="{{ route('admin.accounting.reports.overdue-payables') }}" class="btn btn-outline-warning btn-sm text-start">
                            <i class="ph-warning me-2"></i>
                            بدهی‌های سررسید گذشته
                        </a>
                        <a href="{{ route('admin.accounting.reports.aging-analysis-ap') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-clock me-2"></i>
                            تحلیل سنی پرداختنی‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.supplier-statement') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-scroll me-2"></i>
                            صورتحساب تامین‌کننده
                        </a>
                        <a href="{{ route('admin.accounting.reports.purchase-orders-history') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-shopping-cart me-2"></i>
                            تاریخچه سفارش خرید
                        </a>
                        <a href="{{ route('admin.accounting.reports.supplier-invoices-history') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-invoice me-2"></i>
                            تاریخچه فاکتور خرید
                        </a>
                        <a href="{{ route('admin.accounting.reports.payments-made-history') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-money me-2"></i>
                            تاریخچه پرداخت‌ها
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارش‌های خزانه‌داری -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-vault text-warning me-1"></i>
                        گزارش‌های خزانه‌داری
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.bank-balances') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-bank me-2"></i>
                            موجودی بانک‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.bank-transactions') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-list-numbers me-2"></i>
                            {{ trans('accounting::accounting.reports.bank_statement.title') }}
                        </a>
                        <a href="{{ route('admin.accounting.reports.bank-reconciliation') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-arrows-left-right me-2"></i>
                            {{ trans('accounting::accounting.reports.bank_reconciliation.title') }}
                        </a>
                        <a href="{{ route('admin.accounting.reports.cashbox-balances') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-wallet me-2"></i>
                            موجودی صندوق‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.cheques-received') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-receipt me-2"></i>
                            چک‌های دریافتی
                        </a>
                        <a href="{{ route('admin.accounting.reports.cheques-issued') }}" class="btn btn-outline-warning btn-sm text-start">
                            <i class="ph-note me-2"></i>
                            چک‌های پرداختی
                        </a>
                        <a href="{{ route('admin.accounting.reports.cheque-reminders') }}" class="btn btn-outline-dark btn-sm text-start">
                            <i class="ph-bell me-2"></i>
                            یادآوری چک‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.cash-transactions') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-coins me-2"></i>
                            تراکنش‌های نقدی
                        </a>
                        <a href="{{ route('admin.accounting.reports.pos-report') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-credit-card me-2"></i>
                            گزارش POS
                        </a>
                        <a href="{{ route('admin.accounting.reports.wallet-report') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-wallet me-2"></i>
                            {{ trans('accounting::accounting.reports.wallet_report.title') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- گزارش‌های مالیاتی -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-percent text-info me-1"></i>
                        گزارش‌های مالیاتی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.vat-report') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-file-text me-2"></i>
                            گزارش مالیات بر ارزش افزوده
                        </a>
                        <a href="{{ route('admin.accounting.reports.vat-payable') }}" class="btn btn-outline-danger btn-sm text-start">
                            <i class="ph-arrow-up me-2"></i>
                            مالیات قابل پرداخت
                        </a>
                        <a href="{{ route('admin.accounting.reports.vat-receivable') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-arrow-down me-2"></i>
                            مالیات قابل دریافت
                        </a>
                        <a href="{{ route('admin.accounting.reports.vat-compliance') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-file-arrow-up me-2"></i>
                            تسویه VAT و اظهارنامه 169
                        </a>
                        <a href="{{ route('admin.accounting.reports.taxable-transactions') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-list me-2"></i>
                            تراکنش‌های مشمول مالیات
                        </a>
                        <a href="{{ route('admin.accounting.reports.income-tax-report') }}" class="btn btn-outline-dark btn-sm text-start">
                            <i class="ph-bank me-2"></i>
                            مالیات بر درآمد
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارش‌های هزینه -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-credit-card text-danger me-1"></i>
                        گزارش‌های هزینه
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.expense-summary') }}" class="btn btn-outline-danger btn-sm text-start">
                            <i class="ph-chart-pie me-2"></i>
                            خلاصه هزینه‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.expense-by-category') }}" class="btn btn-outline-warning btn-sm text-start">
                            <i class="ph-folders me-2"></i>
                            هزینه به تفکیک دسته
                        </a>
                        <a href="{{ route('admin.accounting.reports.expense-monthly') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-calendar me-2"></i>
                            هزینه‌های ماهانه
                        </a>
                        <a href="{{ route('admin.accounting.reports.top-expenses') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-trending-up me-2"></i>
                            بیشترین هزینه‌ها
                        </a>
                        <a href="{{ route('admin.accounting.reports.recurring-expenses') }}" class="btn btn-outline-dark btn-sm text-start">
                            <i class="ph-repeat me-2"></i>
                            هزینه‌های تکراری
                        </a>
                        <a href="{{ route('admin.accounting.reports.expense-vs-budget') }}" class="btn btn-outline-dark btn-sm text-start">
                            <i class="ph-scales me-2"></i>
                            هزینه در برابر بودجه
                        </a>
                        @if(Route::has('admin.accounting.utility-bills.index'))
                            <a href="{{ route('admin.accounting.utility-bills.index') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="ph-receipt me-2"></i>
                                {{ trans('accounting::accounting.utility_bills.hub.title') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- گزارش‌های تحلیلی -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph-graph text-primary me-1"></i>
                        گزارش‌های تحلیلی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.accounting.reports.financial-ratios') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="ph-calculator me-2"></i>
                            نسبت‌های مالی
                        </a>
                        <a href="{{ route('admin.accounting.reports.profitability-analysis') }}" class="btn btn-outline-success btn-sm text-start">
                            <i class="ph-trend-up me-2"></i>
                            تحلیل سودآوری
                        </a>
                        <a href="{{ route('admin.accounting.reports.cash-flow-forecast') }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="ph-compass me-2"></i>
                            پیش‌بینی جریان نقدینگی
                        </a>
                        <a href="{{ route('admin.accounting.reports.period-comparison') }}" class="btn btn-outline-warning btn-sm text-start">
                            <i class="ph-arrows-left-right me-2"></i>
                            مقایسه دوره‌ای
                        </a>
                        <a href="{{ route('admin.accounting.reports.revenue-trend') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="ph-chart-line me-2"></i>
                            روند درآمد
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ارزی / COGS / فروش -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ph-currency-circle-dollar text-primary me-1"></i> ارزی و نرخ</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.currency-transactions') }}" class="btn btn-outline-primary btn-sm text-start"><i class="ph-arrows-left-right me-2"></i>تراکنش‌های ارزی</a>
                    <a href="{{ route('admin.accounting.reports.fx-gain-loss') }}" class="btn btn-outline-primary btn-sm text-start"><i class="ph-chart-line me-2"></i>سود و زیان تسعیر</a>
                    <a href="{{ route('admin.accounting.reports.fx-rates-used') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-hash me-2"></i>نرخ‌های استفاده‌شده</a>
                    <a href="{{ route('admin.accounting.reports.foreign-purchases') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-globe me-2"></i>خریدهای ارزی</a>
                    <a href="{{ route('admin.accounting.reports.currency-balances') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-stack me-2"></i>مانده ارزی</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ph-package text-warning me-1"></i> COGS</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.cogs-report') }}" class="btn btn-outline-warning btn-sm text-start"><i class="ph-tag me-2"></i>گزارش بهای تمام‌شده</a>
                    <a href="{{ route('admin.accounting.reports.product-profitability') }}" class="btn btn-outline-warning btn-sm text-start"><i class="ph-chart-pie me-2"></i>سودآوری محصول</a>
                    <a href="{{ route('admin.accounting.reports.sales-vs-cogs') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-scales me-2"></i>فروش در برابر COGS</a>
                    <a href="{{ route('admin.accounting.reports.cogs-monthly-trend') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-calendar me-2"></i>روند ماهانه COGS</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ph-shopping-cart text-success me-1"></i> فروش</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.sales-summary') }}" class="btn btn-outline-success btn-sm text-start"><i class="ph-chart-bar me-2"></i>خلاصه فروش</a>
                    <a href="{{ route('admin.accounting.reports.sales-by-customer') }}" class="btn btn-outline-success btn-sm text-start"><i class="ph-users me-2"></i>فروش به تفکیک مشتری</a>
                    <a href="{{ route('admin.accounting.reports.sales-by-product') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-cube me-2"></i>فروش به تفکیک کالا</a>
                    <a href="{{ route('admin.accounting.reports.sales-trend') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-trend-up me-2"></i>روند فروش</a>
                </div>
            </div>
        </div>
    </div>

    <!-- تطبیق / سال مالی / Audit / Party -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ph-checks me-1"></i> تطبیق</h5></div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.cashbox-reconciliation') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-vault me-2"></i>تطبیق صندوق</a>
                    <a href="{{ route('admin.accounting.reports.unreconciled-items') }}" class="btn btn-outline-warning btn-sm text-start"><i class="ph-warning me-2"></i>اقلام تطبیق‌نشده</a>
                    <a href="{{ route('admin.accounting.reports.reconciliation-history') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-clock me-2"></i>تاریخچه تطبیق</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ph-calendar-check me-1"></i> سال مالی</h5></div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.fiscal-year-performance') }}" class="btn btn-outline-primary btn-sm text-start"><i class="ph-chart-line me-2"></i>عملکرد سال مالی</a>
                    <a href="{{ route('admin.accounting.reports.year-over-year') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-arrows-out-line-vertical me-2"></i>سال به سال</a>
                    <a href="{{ route('admin.accounting.reports.closing-report') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-lock me-2"></i>گزارش بستن</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ph-shield-check me-1"></i> ممیزی</h5></div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.accounting.reports.audit-trail') }}" class="btn btn-outline-dark btn-sm text-start"><i class="ph-footprints me-2"></i>ردپای تغییرات</a>
                    <a href="{{ route('admin.accounting.reports.document-reversals') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-arrow-u-up-left me-2"></i>اسناد اصلاحی</a>
                    <a href="{{ route('admin.accounting.reports.accounting-activity-log') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-list me-2"></i>لاگ فعالیت</a>
                    <a href="{{ route('admin.accounting.reports.discrepancies') }}" class="btn btn-outline-danger btn-sm text-start"><i class="ph-warning-octagon me-2"></i>مغایرت‌ها</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ph-handshake me-1"></i> طرف تجاری (Party)</h5></div>
                <div class="card-body d-grid gap-2">
                    <p class="small text-body-secondary mb-0">برخی مسیرها نیاز به شناسه در URL دارند؛ از لیست طرف‌ها استفاده کنید.</p>
                    <a href="{{ route('admin.accounting.reports.party-balances') }}" class="btn btn-outline-primary btn-sm text-start"><i class="ph-users me-2"></i>مانده طرف‌ها</a>
                    <a href="{{ route('admin.accounting.reports.all-parties-profitability') }}" class="btn btn-outline-success btn-sm text-start"><i class="ph-chart-line me-2"></i>سودآوری همه طرف‌ها</a>
                    <a href="{{ route('admin.accounting.reports.parties-with-both-roles') }}" class="btn btn-outline-secondary btn-sm text-start"><i class="ph-arrows-merge me-2"></i>طرف با دو نقش</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
