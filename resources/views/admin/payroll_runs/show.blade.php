@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.payroll_runs.show_title'))
@section('content')
@php
    $journalDisp = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
    $loanPrincipalTotal = (float) $run->lineItems->where('code', 'loan_principal')->sum('amount');
    $loanInterestTotal = (float) $run->lineItems->where('code', 'loan_interest')->sum('amount');
    $hasLoanDeduction = ($loanPrincipalTotal + $loanInterestTotal) > 0;
    $hasSeniorityAccrual = (float) $run->total_seniority > 0;
    $netPaymentCheque = $paymentCheques['payroll_net_payment'] ?? null;
    $seniorityPaymentCheque = $paymentCheques['payroll_seniority_payment'] ?? null;
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $run->run_number }} - {{ $run->title }}</h4>
        <a href="{{ route('admin.accounting.payroll-runs.index') }}" class="btn btn-secondary">{{ trans('accounting::accounting.common.back') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.status') }}</small><strong>{{ trans('accounting::accounting.payroll_runs.statuses.'.$run->status) }}</strong></div></div></div>
        <div class="col-lg-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.total_gross') }}</small><strong>{{ number_format((float) $run->total_gross, 0) }}</strong></div></div></div>
        <div class="col-lg-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.fields.seniority_reserve') }}</small><strong>{{ number_format((float) $run->total_seniority, 0) }}</strong></div></div></div>
        <div class="col-lg-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.total_net') }}</small><strong>{{ number_format((float) $run->total_net, 0) }}</strong></div></div></div>
        <div class="col-lg-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.period') }}</small><strong>{{ $run->period_start?->format('Y-m-d') }} — {{ $run->period_end?->format('Y-m-d') }}</strong></div></div></div>
    </div>
    @if($hasLoanDeduction)
    <div class="alert alert-warning mb-3">
        <div class="fw-semibold mb-1">{{ trans('accounting::accounting.payroll_runs.fields.loan_deductions') }}</div>
        <div class="small">
            {{ trans('accounting::accounting.payroll_runs.items.loan_principal') }}: <strong>{{ number_format($loanPrincipalTotal, 0) }}</strong>
            &nbsp;|&nbsp;
            {{ trans('accounting::accounting.payroll_runs.items.loan_interest') }}: <strong>{{ number_format($loanInterestTotal, 0) }}</strong>
        </div>
    </div>
    @endif

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.employee') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.base_salary') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.benefits') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.seniority') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.employee_insurance') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.employer_insurance') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.tax') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.other_deductions') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.fields.net_salary') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($run->lines as $line)
                        <tr>
                            <td>{{ $line->line_number }}</td>
                            <td>{{ $line->employee?->name }}</td>
                            <td>{{ number_format((float) $line->base_salary, 0) }}</td>
                            <td>{{ number_format((float) $line->benefits, 0) }}</td>
                            <td>{{ number_format((float) $line->seniority, 0) }}</td>
                            <td>{{ number_format((float) $line->employee_insurance, 0) }}</td>
                            <td>{{ number_format((float) $line->employer_insurance, 0) }}</td>
                            <td>{{ number_format((float) $line->tax, 0) }}</td>
                            <td>{{ number_format((float) $line->other_deductions, 0) }}</td>
                            <td>{{ number_format((float) $line->net_salary, 0) }}</td>
                            <td>
                                <a href="{{ route('admin.accounting.payroll-runs.payslips.print', [$run->id, $line->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">
                                    {{ trans('accounting::accounting.payroll_runs.actions.print_payslip') }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="11" class="bg-light">
                                <div class="small fw-semibold mb-1">{{ trans('accounting::accounting.payroll_runs.items.title') }}</div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ trans('accounting::accounting.payroll_runs.items.fields.type') }}</th>
                                                <th>{{ trans('accounting::accounting.payroll_runs.items.fields.code') }}</th>
                                                <th>{{ trans('accounting::accounting.payroll_runs.items.fields.title') }}</th>
                                                <th>{{ trans('accounting::accounting.payroll_runs.items.fields.amount') }}</th>
                                                <th>{{ trans('accounting::accounting.payroll_runs.items.fields.notes') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($line->items as $item)
                                                <tr>
                                                    <td>{{ trans('accounting::accounting.payroll_runs.items.types.'.$item->type) }}</td>
                                                    <td><code>{{ $item->code }}</code></td>
                                                    <td>{{ $item->title }}</td>
                                                    <td>{{ number_format((float) $item->amount, 0) }}</td>
                                                    <td>{{ $item->notes }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-muted">{{ trans('accounting::accounting.payroll_runs.items.empty') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_accrual') }}</h6>
                @if($run->accrual_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->accrual_manual_journal_id }}</strong>
                    </div>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.accrual.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.accrual', $run->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100">
                            {{ trans('accounting::accounting.payroll_runs.actions.post') }}
                        </button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_net_payment') }}</h6>
                @if($run->net_payment_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->net_payment_manual_journal_id }}</strong>
                    </div>
                    <ul class="list-unstyled small mb-2">
                        <li>مبلغ: <strong>{{ number_format((float) $run->total_net, 0) }}</strong></li>
                        @if($netPaymentCheque)
                            <li>روش: <strong>چک پرداختی</strong></li>
                            <li>شماره چک: <strong>{{ $netPaymentCheque->cheque_number }}</strong></li>
                            <li>در وجه: <strong>{{ $netPaymentCheque->payee_name }}</strong></li>
                            <li>
                                <a href="{{ route('admin.accounting.cheques.edit', $netPaymentCheque->id) }}" class="btn btn-sm btn-outline-primary mt-1">
                                    مشاهده چک
                                </a>
                            </li>
                        @else
                            <li>روش: <strong>بانکی</strong></li>
                        @endif
                    </ul>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.net-payment.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.net-payment', $run->id) }}" class="js-pr-payment-form">
                        @csrf
                        <x-accounting::date-field name="journal_date" :label="trans('accounting::accounting.payroll_runs.fields.journal_date')" :value="$journalDisp" :required="true" col-class="col-12 mb-2" />
                        <select name="payment_method" class="form-select mb-2 js-pr-payment-method" data-bank-label="{{ trans('accounting::accounting.payroll_runs.fields.bank') }}">
                            <option value="bank">{{ trans('accounting::accounting.payroll_runs.fields.payment_method_bank') }}</option>
                            <option value="cheque">{{ trans('accounting::accounting.payroll_runs.fields.payment_method_cheque') }}</option>
                        </select>
                        <div class="js-pr-payment-bank-field">
                            <select name="bank_id" class="form-select mb-2" required>
                                <option value="">{{ trans('accounting::accounting.payroll_runs.fields.bank') }}</option>
                                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>@endforeach
                            </select>
                        </div>
                        <div class="js-pr-payment-cheque-fields d-none">
                            <select name="chequebook_id" class="form-select mb-2">
                                <option value="">{{ trans('accounting::accounting.payroll_runs.fields.chequebook') }}</option>
                                @foreach($chequebooks as $chequebook)
                                    <option value="{{ $chequebook->id }}">{{ $chequebook->title }} @if($chequebook->bank) — {{ $chequebook->bank->name }} @endif</option>
                                @endforeach
                            </select>
                            <input type="text" name="cheque_number" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_number') }}">
                            <x-accounting::date-field name="cheque_due_date" :label="trans('accounting::accounting.payroll_runs.fields.cheque_due_date')" :value="$journalDisp" :required="false" col-class="col-12 mb-2" />
                            <input type="text" name="cheque_payee_name" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_payee_name') }}">
                            <input type="text" name="cheque_notes" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_notes') }}">
                        </div>
                        <input type="text" name="description" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.description') }}">
                        <button type="submit" class="btn btn-warning text-dark w-100" @disabled(!$run->accrual_manual_journal_id)>{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_insurance_remittance') }}</h6>
                @if($run->insurance_remittance_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->insurance_remittance_manual_journal_id }}</strong>
                    </div>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.insurance-remittance.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.insurance-remittance', $run->id) }}">
                        @csrf
                        <x-accounting::date-field name="journal_date" :label="trans('accounting::accounting.payroll_runs.fields.journal_date')" :value="$journalDisp" :required="true" col-class="col-12 mb-2" />
                        <select name="bank_id" class="form-select mb-2" required>
                            <option value="">{{ trans('accounting::accounting.payroll_runs.fields.bank') }}</option>
                            @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>@endforeach
                        </select>
                        <input type="text" name="description" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.description') }}">
                        <button type="submit" class="btn btn-info text-dark w-100" @disabled(!$run->accrual_manual_journal_id)>{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_tax_remittance') }}</h6>
                @if($run->tax_remittance_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->tax_remittance_manual_journal_id }}</strong>
                    </div>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.tax-remittance.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.tax-remittance', $run->id) }}">
                        @csrf
                        <x-accounting::date-field name="journal_date" :label="trans('accounting::accounting.payroll_runs.fields.journal_date')" :value="$journalDisp" :required="true" col-class="col-12 mb-2" />
                        <select name="bank_id" class="form-select mb-2" required>
                            <option value="">{{ trans('accounting::accounting.payroll_runs.fields.bank') }}</option>
                            @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>@endforeach
                        </select>
                        <input type="text" name="description" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.description') }}">
                        <button type="submit" class="btn btn-dark w-100" @disabled(!$run->accrual_manual_journal_id)>{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_loan_settlement') }}</h6>
                @if($run->loan_settlement_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->loan_settlement_manual_journal_id }}</strong>
                    </div>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.loan-settlement.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.loan-settlement', $run->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-success w-100" @disabled(!$hasLoanDeduction || !$run->accrual_manual_journal_id)>{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                    </form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h6>{{ trans('accounting::accounting.payroll_runs.actions.post_seniority_settlement') }}</h6>
                @if($run->seniority_settlement_manual_journal_id)
                    <div class="alert alert-success py-2 small mb-2">
                        سند ثبت شده: <strong>#{{ $run->seniority_settlement_manual_journal_id }}</strong>
                    </div>
                    <ul class="list-unstyled small mb-2">
                        <li>مبلغ: <strong>{{ number_format((float) $run->total_seniority, 0) }}</strong></li>
                        @if($seniorityPaymentCheque)
                            <li>روش: <strong>چک پرداختی</strong></li>
                            <li>شماره چک: <strong>{{ $seniorityPaymentCheque->cheque_number }}</strong></li>
                            <li>در وجه: <strong>{{ $seniorityPaymentCheque->payee_name }}</strong></li>
                            <li>
                                <a href="{{ route('admin.accounting.cheques.edit', $seniorityPaymentCheque->id) }}" class="btn btn-sm btn-outline-primary mt-1">
                                    مشاهده چک
                                </a>
                            </li>
                        @else
                            <li>روش: <strong>بانکی</strong></li>
                        @endif
                    </ul>
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.seniority-settlement.reverse', $run->id) }}" class="mt-2 js-pr-reverse-form" data-confirm-title="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_title') }}" data-confirm-message="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_message') }}" data-confirm-button="{{ trans('accounting::accounting.payroll_runs.actions.reverse_confirm_button') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">{{ trans('accounting::accounting.payroll_runs.actions.reverse') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.accounting.payroll-runs.seniority-settlement', $run->id) }}" class="js-pr-payment-form">
                        @csrf
                        <x-accounting::date-field name="journal_date" :label="trans('accounting::accounting.payroll_runs.fields.journal_date')" :value="$journalDisp" :required="true" col-class="col-12 mb-2" />
                        <select name="payment_method" class="form-select mb-2 js-pr-payment-method" data-bank-label="{{ trans('accounting::accounting.payroll_runs.fields.bank') }}">
                            <option value="bank">{{ trans('accounting::accounting.payroll_runs.fields.payment_method_bank') }}</option>
                            <option value="cheque">{{ trans('accounting::accounting.payroll_runs.fields.payment_method_cheque') }}</option>
                        </select>
                        <div class="js-pr-payment-bank-field">
                            <select name="bank_id" class="form-select mb-2" required>
                                <option value="">{{ trans('accounting::accounting.payroll_runs.fields.bank') }}</option>
                                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>@endforeach
                            </select>
                        </div>
                        <div class="js-pr-payment-cheque-fields d-none">
                            <select name="chequebook_id" class="form-select mb-2">
                                <option value="">{{ trans('accounting::accounting.payroll_runs.fields.chequebook') }}</option>
                                @foreach($chequebooks as $chequebook)
                                    <option value="{{ $chequebook->id }}">{{ $chequebook->title }} @if($chequebook->bank) — {{ $chequebook->bank->name }} @endif</option>
                                @endforeach
                            </select>
                            <input type="text" name="cheque_number" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_number') }}">
                            <x-accounting::date-field name="cheque_due_date" :label="trans('accounting::accounting.payroll_runs.fields.cheque_due_date')" :value="$journalDisp" :required="false" col-class="col-12 mb-2" />
                            <input type="text" name="cheque_payee_name" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_payee_name') }}">
                            <input type="text" name="cheque_notes" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.cheque_notes') }}">
                        </div>
                        <input type="text" name="description" class="form-control mb-2" placeholder="{{ trans('accounting::accounting.payroll_runs.fields.description') }}">
                        <button type="submit" class="btn btn-outline-primary w-100" @disabled(!$hasSeniorityAccrual || !$run->accrual_manual_journal_id)>{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                    </form>
                @endif
            </div></div>
        </div>
    </div>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
