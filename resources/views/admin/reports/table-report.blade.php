@extends('cms::admin.layout.index')
@section('title', $data['title'] ?? 'گزارش')
@section('content')
@php
    $periodStart = isset($data['period']['start']) ? (string) $data['period']['start'] : null;
    $periodEnd = isset($data['period']['end']) ? (string) $data['period']['end'] : null;
    $reportBaseCurrency = strtoupper((string) config('accounting.default_currency', 'IRR'));
    $reportFromVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeFromDisplay(
        request('from_date'),
        request('start_date'),
        $periodStart
    );
    $reportToVal = \RMS\Accounting\Support\AccountingDateUi::reportRangeToDisplay(
        request('to_date'),
        request('end_date'),
        $periodEnd
    );
@endphp
<div class="container-fluid">
    @include('accounting::admin.reports.partials.report-filter-card', [
        'data' => $data,
        'reportFromVal' => $reportFromVal,
        'reportToVal' => $reportToVal,
    ])
    @include('accounting::admin.reports.partials.report-messages')
    @include('accounting::admin.reports.partials.report-treasury-negative-ledger-alert')
    @if(isset($data['payroll_insurance_payable_summary']) && is_array($data['payroll_insurance_payable_summary']))
        @php
            $insuranceSummary = $data['payroll_insurance_payable_summary'];
        @endphp
        @if(!empty($insuranceSummary['items']))
            <div class="alert alert-info border-info-subtle mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <strong>{{ $insuranceSummary['title'] }}</strong>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                        {{ trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.total_label') }}:
                        {{ number_format((float) ($insuranceSummary['total'] ?? 0)) }}
                    </span>
                </div>
                <div class="small text-muted mb-2">
                    {{ trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.as_of_label') }}
                    {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse((string) ($insuranceSummary['as_of'] ?? now())), 'Y/m/d H:i') }}
                </div>
                <ul class="mb-0 ps-3">
                    @foreach($insuranceSummary['items'] as $item)
                        <li class="mb-1">
                            <strong>{{ $item['code'] }}</strong> — {{ $item['name'] }}
                            ({{ trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.balance_label') }}:
                            {{ number_format((float) ($item['balance'] ?? 0)) }})
                            <a class="ms-1" href="{{ route('admin.accounting.reports.subsidiary-ledger', ['account_id' => $item['account_id']]) }}">
                                {{ trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.drilldown') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if(isset($insuranceSummary['legacy']))
                    @php
                        $legacy = $insuranceSummary['legacy'];
                    @endphp
                    @if(abs((float) ($legacy['balance'] ?? 0)) > 0.009)
                        <div class="small mt-2">
                            {{ trans('accounting::accounting.reports.general_ledger.insurance_payable_summary.legacy_hint', ['code' => $legacy['code']]) }}
                            <strong>{{ number_format((float) ($legacy['balance'] ?? 0)) }}</strong>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    @endif

    @if(!isset($data['error']) && !isset($data['message']))
    <!-- گزارش -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">{{ $data['title'] }}</h4>
                    @if(isset($data['period']))
                    <p class="text-muted mb-0">
                        <i class="ph-calendar me-1"></i>
                        دوره:
                        {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['start']), 'Y/m/d') }}
                        تا
                        {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($data['period']['end']), 'Y/m/d') }}
                    </p>
                    @endif
                    @if(isset($data['as_of_date']))
                    <p class="text-muted mb-0">
                        <i class="ph-calendar me-1"></i>
                        تا تاریخ: {{ $data['as_of_date'] }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(isset($data['output_vat']) && isset($data['input_vat']) && array_key_exists('vat_payable', $data))
                @php
                    $vatRate = (float) ($data['settings']['vat_rate'] ?? 0);
                    $vatMethod = (string) ($data['settings']['method'] ?? 'exclusive');
                    $vatEnabled = (bool) ($data['settings']['enabled'] ?? true);
                    $outputSales = (float) ($data['output_vat']['sales'] ?? 0);
                    $outputVat = (float) ($data['output_vat']['vat'] ?? 0);
                    $inputPurchases = (float) ($data['input_vat']['purchases'] ?? 0);
                    $inputVat = (float) ($data['input_vat']['vat'] ?? 0);
                    $vatNet = (float) ($data['vat_payable'] ?? 0);
                    $remittedVat = (float) ($data['remitted_vat'] ?? 0);
                    $remainingVat = (float) ($data['net_payable_remaining'] ?? $vatNet);
                @endphp
                <div class="alert {{ $vatEnabled ? 'alert-info' : 'alert-warning' }} mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <strong>{{ trans('accounting::accounting.reports.vat.settings_title') }}</strong>
                            <div class="small text-muted mt-1">
                                {{ trans('accounting::accounting.reports.vat.settings_rate') }}: {{ number_format($vatRate, 2) }}%
                                — {{ trans('accounting::accounting.reports.vat.settings_method') }}:
                                {{ $vatMethod === 'inclusive'
                                    ? trans('accounting::accounting.reports.vat.method_inclusive')
                                    : trans('accounting::accounting.reports.vat.method_exclusive') }}
                            </div>
                        </div>
                        @if(!$vatEnabled)
                            <span class="badge bg-warning text-dark align-self-start">{{ trans('accounting::accounting.reports.vat.settings_disabled') }}</span>
                        @endif
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border-primary h-100">
                            <div class="card-body">
                                <div class="text-muted small">{{ trans('accounting::accounting.reports.vat.output_vat') }}</div>
                                <h5 class="mb-1">{{ number_format($outputVat) }}</h5>
                                <div class="small text-muted">{{ trans('accounting::accounting.reports.vat.taxable_sales') }}: {{ number_format($outputSales) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success h-100">
                            <div class="card-body">
                                <div class="text-muted small">{{ trans('accounting::accounting.reports.vat.input_vat') }}</div>
                                <h5 class="mb-1">{{ number_format($inputVat) }}</h5>
                                <div class="small text-muted">{{ trans('accounting::accounting.reports.vat.taxable_purchases') }}: {{ number_format($inputPurchases) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card {{ $vatNet >= 0 ? 'border-danger' : 'border-warning' }} h-100">
                            <div class="card-body">
                                <div class="text-muted small">{{ $vatNet >= 0 ? trans('accounting::accounting.reports.vat.net_payable') : trans('accounting::accounting.reports.vat.net_receivable') }}</div>
                                <h5 class="mb-0">{{ number_format(abs($vatNet)) }}</h5>
                                <div class="small text-muted mt-1">پرداخت‌شده: {{ number_format($remittedVat) }}</div>
                                <div class="small text-muted">مانده: {{ number_format($remainingVat) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif(array_key_exists('vat_payable', $data) || array_key_exists('vat_receivable', $data))
                @php
                    $singleVatValue = (float) ($data['vat_payable'] ?? $data['vat_receivable'] ?? 0);
                    $singleVatLabel = array_key_exists('vat_payable', $data)
                        ? trans('accounting::accounting.reports.vat.payable_label')
                        : trans('accounting::accounting.reports.vat.receivable_label');
                @endphp
                <div class="alert alert-info">
                    <strong>{{ $singleVatLabel }}:</strong>
                    <span class="ms-2">{{ number_format($singleVatValue) }}</span>
                    @if(array_key_exists('remitted_vat', $data))
                        <span class="ms-3">پرداخت‌شده: {{ number_format((float) ($data['remitted_vat'] ?? 0)) }}</span>
                    @endif
                    @if(array_key_exists('net_payable_remaining', $data))
                        <span class="ms-3">مانده: {{ number_format((float) ($data['net_payable_remaining'] ?? 0)) }}</span>
                    @endif
                </div>
            @endif

            @if(isset($data['accounts_tree']))
            <div class="alert alert-light border mb-3 py-2 small" role="status">
                <i class="ph-tree me-1"></i>
                نمای پیش‌فرض: فقط <strong>حساب‌های ریشه</strong> با جمع تجمیعی؛ زیرشاخه‌ها هنگام باز کردن هر بخش <strong>به‌صورت AJAX</strong> بارگذاری می‌شوند تا صفحه سبک بماند.
                <a href="{{ request()->fullUrlWithQuery(array_merge(request()->query(), ['flat' => '1'])) }}" class="alert-link ms-1">نمایش لیست تخت (هر حساب با گردش مستقیم)</a>
            </div>
            @if(!empty($data['lazy_branch']))
            <div id="gl-report-ajax-meta" class="d-none"
                 data-branch-url="{{ route('admin.accounting.reports.general-ledger-branch') }}"
                 data-from-date="{{ $reportFromVal }}"
                 data-to-date="{{ $reportToVal }}"></div>
            @endif
            @endif
            @if(!empty($data['flat_list']))
            @php
                $glTreeQuery = request()->query();
                unset($glTreeQuery['flat']);
            @endphp
            <div class="alert alert-light border mb-3 py-2 small">
                <a href="{{ request()->fullUrlWithQuery($glTreeQuery) }}" class="alert-link">
                    <i class="ph-tree me-1"></i>بازگشت به نمای درختی با تجمیع در والد
                </a>
            </div>
            @endif
            @php
                $isVatSummaryOnly =
                    (isset($data['output_vat']) && isset($data['input_vat']) && array_key_exists('vat_payable', $data))
                    || (array_key_exists('vat_payable', $data) && !isset($data['rows']) && !isset($data['accounts']) && !isset($data['accounts_tree']))
                    || (array_key_exists('vat_receivable', $data) && !isset($data['rows']) && !isset($data['accounts']) && !isset($data['accounts_tree']));
            @endphp
            @if(!$isVatSummaryOnly || isset($data['rows']) || isset($data['accounts_tree']) || isset($data['accounts']) || isset($data['customers']) || isset($data['suppliers']) || isset($data['banks']) || isset($data['cashboxes']) || isset($data['cheques']) || isset($data['categories']))
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            @if(isset($data['columns']))
                                @foreach($data['columns'] as $col)
                                <th>{{ $col }}</th>
                                @endforeach
                            @elseif(isset($data['accounts_tree']))
                                <th>کد</th>
                                <th>نام حساب</th>
                                <th class="text-end">بدهکار (تجمیعی)</th>
                                <th class="text-end">بستانکار (تجمیعی)</th>
                                <th class="text-end">مانده</th>
                            @elseif(isset($data['accounts']) && !empty($data['trial_balance_extended']))
                                <th>کد</th>
                                <th>نام حساب</th>
                                <th class="text-end">افتتاحیه بدهکار</th>
                                <th class="text-end">افتتاحیه بستانکار</th>
                                <th class="text-end">گردش بدهکار</th>
                                <th class="text-end">گردش بستانکار</th>
                                <th class="text-end">اختتامیه بدهکار</th>
                                <th class="text-end">اختتامیه بستانکار</th>
                            @elseif(isset($data['accounts']))
                                <th>کد</th>
                                <th>نام حساب</th>
                                <th class="text-end">بدهکار</th>
                                <th class="text-end">بستانکار</th>
                                <th class="text-end">مانده</th>
                            @elseif(isset($data['entries']))
                                <th>تاریخ</th>
                                <th>شماره سند</th>
                                <th>شرح</th>
                                <th class="text-end">بدهکار</th>
                                <th class="text-end">بستانکار</th>
                                <th class="text-end">مانده جاری</th>
                            @elseif(isset($data['customers']))
                                <th>مشتری</th>
                                <th class="text-end">تعداد فاکتور</th>
                                <th class="text-end">مبلغ کل</th>
                                <th class="text-end">پرداخت شده</th>
                                <th class="text-end">مانده</th>
                            @elseif(isset($data['suppliers']))
                                <th>تامین‌کننده</th>
                                <th class="text-end">مانده</th>
                            @elseif(isset($data['banks']))
                                <th>نام بانک</th>
                                <th>شماره حساب</th>
                                <th class="text-end">موجودی</th>
                            @elseif(isset($data['cashboxes']))
                                <th>نام صندوق</th>
                                <th class="text-end">موجودی</th>
                            @elseif(isset($data['cheques']))
                                <th>شماره چک</th>
                                <th>بانک</th>
                                <th>سررسید</th>
                                <th class="text-end">مبلغ</th>
                                <th>وضعیت</th>
                            @elseif(isset($data['categories']))
                                <th>دسته</th>
                                <th class="text-end">مبلغ</th>
                                <th class="text-end">درصد</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($data['rows']))
                            @foreach($data['rows'] as $row)
                            <tr>
                                @foreach($row as $value)
                                <td class="{{ is_numeric($value) ? 'text-end' : '' }}">
                                    {{ is_numeric($value) ? number_format($value) : $value }}
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        @elseif(isset($data['accounts_tree']))
                            @include('accounting::admin.reports.partials.general-ledger-tree-level', ['nodes' => $data['accounts_tree']])
                        @elseif(isset($data['accounts']) && !empty($data['trial_balance_extended']))
                            @foreach($data['accounts'] as $account)
                            @php
                                $accountCurrency = strtoupper((string) ($account['currency_code'] ?? ''));
                                $showAccountCurrency = $accountCurrency !== '' && $accountCurrency !== $reportBaseCurrency;
                            @endphp
                            <tr>
                                <td>{{ $account['code'] }}</td>
                                <td>
                                    {{ $account['name'] }}
                                    @if($showAccountCurrency)
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle ms-1">{{ $accountCurrency }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) ($account['opening_debit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($account['opening_credit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($account['period_debit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($account['period_credit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($account['ending_debit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($account['ending_credit'] ?? 0)) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['accounts']))
                            @foreach($data['accounts'] as $account)
                            @php
                                $accountCurrency = strtoupper((string) ($account['currency_code'] ?? ''));
                                $showAccountCurrency = $accountCurrency !== '' && $accountCurrency !== $reportBaseCurrency;
                            @endphp
                            <tr>
                                <td>{{ $account['code'] }}</td>
                                <td>
                                    {!! str_repeat('&nbsp;&nbsp;', max(0, ($account['level'] ?? 1) - 1)) !!}{{ $account['name'] }}
                                    @if($showAccountCurrency)
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle ms-1">{{ $accountCurrency }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($account['debit']) }}</td>
                                <td class="text-end">{{ number_format($account['credit']) }}</td>
                                <td class="text-end">{{ number_format($account['balance']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['entries']))
                            @foreach($data['entries'] as $entry)
                            <tr>
                                <td>{{ $entry['date'] ?? '-' }}</td>
                                <td>{{ $entry['document_number'] ?? '-' }}</td>
                                <td>{{ $entry['description'] ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) ($entry['debit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($entry['credit'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($entry['balance'] ?? 0)) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['customers']))
                            @foreach($data['customers'] as $customer)
                            <tr>
                                <td>{{ $customer['customer_name'] }}</td>
                                <td class="text-end">{{ $customer['total_invoices'] ?? 0 }}</td>
                                <td class="text-end">{{ number_format($customer['total_amount'] ?? $customer['balance']) }}</td>
                                <td class="text-end">{{ number_format($customer['paid_amount'] ?? 0) }}</td>
                                <td class="text-end">{{ number_format($customer['balance']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['suppliers']))
                            @foreach($data['suppliers'] as $supplier)
                            <tr>
                                <td>{{ $supplier['supplier_name'] }}</td>
                                <td class="text-end">{{ number_format($supplier['balance']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['banks']))
                            @foreach($data['banks'] as $bank)
                            <tr>
                                <td>{{ $bank['name'] }}</td>
                                <td>{{ $bank['account_number'] }}</td>
                                <td class="text-end">{{ number_format($bank['balance']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['cashboxes']))
                            @foreach($data['cashboxes'] as $cashbox)
                            <tr>
                                <td>{{ $cashbox['name'] }}</td>
                                <td class="text-end">{{ number_format($cashbox['balance']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['cheques']))
                            @foreach($data['cheques'] as $cheque)
                            <tr>
                                <td>{{ $cheque->cheque_number }}</td>
                                <td>{{ $cheque->bank_name }}</td>
                                <td>{{ $cheque->due_date }}</td>
                                <td class="text-end">{{ number_format($cheque->amount) }}</td>
                                <td>
                                    @if($cheque->status === 'pending')
                                    <span class="badge bg-warning">در انتظار</span>
                                    @elseif($cheque->status === 'cashed')
                                    <span class="badge bg-success">وصول شده</span>
                                    @else
                                    <span class="badge bg-danger">برگشتی</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        @elseif(isset($data['categories']))
                            @foreach($data['categories'] as $category)
                            <tr>
                                <td>{{ $category['category'] }}</td>
                                <td class="text-end">{{ number_format($category['amount']) }}</td>
                                <td class="text-end">{{ number_format($category['percent'], 1) }}%</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                    @if(isset($data['totals']) || isset($data['summary']))
                    <tfoot class="table-light">
                        @if(isset($data['totals']))
                        <tr class="fw-bold">
                            <td colspan="{{ (isset($data['accounts']) || isset($data['accounts_tree'])) ? 2 : 1 }}">جمع کل</td>
                            @if(!empty($data['trial_balance_extended']))
                            <td class="text-end">{{ number_format((float) ($data['totals']['opening_debit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['totals']['opening_credit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['totals']['period_debit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['totals']['period_credit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['totals']['debit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['totals']['credit'] ?? 0)) }}</td>
                            @elseif(isset($data['totals']['debit']))
                            <td class="text-end">{{ number_format($data['totals']['debit']) }}</td>
                            <td class="text-end">{{ number_format($data['totals']['credit']) }}</td>
                            <td class="text-end">
                                @if($data['totals']['is_balanced'] ?? false)
                                <span class="badge bg-success">متعادل</span>
                                @else
                                <span class="badge bg-danger">نامتعادل</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @endif
                        
                        @if(isset($data['summary']))
                        <tr class="fw-bold">
                            <td>جمع کل</td>
                            @if(isset($data['summary']['total_debit']) || isset($data['summary']['total_credit']) || isset($data['summary']['final_balance']))
                            <td></td>
                            <td></td>
                            <td class="text-end">{{ number_format((float) ($data['summary']['total_debit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['summary']['total_credit'] ?? 0)) }}</td>
                            <td class="text-end">{{ number_format((float) ($data['summary']['final_balance'] ?? 0)) }}</td>
                            @elseif(isset($data['summary']['total_balance']))
                            <td class="text-end" colspan="4">{{ number_format($data['summary']['total_balance']) }}</td>
                            @elseif(isset($data['total']))
                            <td class="text-end" colspan="2">{{ number_format($data['total']) }}</td>
                            @endif
                        </tr>
                        @endif
                    </tfoot>
                    @endif
                </table>
            </div>
            @endif
            
            @if(isset($data['aging_buckets']))
            <!-- نمایش Aging Buckets -->
            <div class="mt-4">
                <h5>تحلیل سنی</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>جاری (0-30 روز)</h6>
                                <h4>{{ number_format($data['aging_buckets']['current']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning">
                            <div class="card-body">
                                <h6>31-60 روز</h6>
                                <h4>{{ number_format($data['aging_buckets']['31_60']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-orange text-white">
                            <div class="card-body">
                                <h6>61-90 روز</h6>
                                <h4>{{ number_format($data['aging_buckets']['61_90']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6>بیش از 90 روز</h6>
                                <h4>{{ number_format($data['aging_buckets']['over_90']) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['accounts_tree']))
                @include('accounting::components.collapse_help_card', [
                    'collapseId' => 'general-ledger-report-help',
                    'cardClass' => 'mt-4',
                    'toggleLabel' => trans('accounting::accounting.reports.general_ledger.help.toggle'),
                    'title' => trans('accounting::accounting.reports.general_ledger.help.title'),
                    'paragraphs' => [
                        trans('accounting::accounting.reports.general_ledger.help.p1'),
                        trans('accounting::accounting.reports.general_ledger.help.p2'),
                        trans('accounting::accounting.reports.general_ledger.help.p3'),
                        trans('accounting::accounting.reports.general_ledger.help.p4'),
                        trans('accounting::accounting.reports.general_ledger.help.p5'),
                    ],
                ])
            @endif
        </div>
        <div class="card-footer">
            <a href="{{ route('admin.accounting.reports.index') }}" class="btn btn-secondary">
                <i class="ph-arrow-left me-1"></i>
                بازگشت به لیست گزارش‌ها
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
