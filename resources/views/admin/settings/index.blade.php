@extends('cms::admin.layout.index')
@section('title', 'تنظیمات حسابداری')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="ph-gear me-2"></i>
                تنظیمات حسابداری (Accounting Settings)
            </h4>
        </div>
        
        <form action="{{ route('admin.accounting.settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="card-body">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#general-tab">
                            <i class="ph-gear me-1"></i>
                            عمومی
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#bank-reconciliation-tab">
                            <i class="ph-arrows-clockwise me-1"></i>
                            {{ trans('accounting::accounting.settings_bank_reconciliation.tab_title') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tax-tab">
                            <i class="ph-percent me-1"></i>
                            مالیات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#currency-tab">
                            <i class="ph-currency-circle-dollar me-1"></i>
                            ارز
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#purchase-forms-tab">
                            <i class="ph-shopping-cart-simple me-1"></i>
                            {{ trans('accounting::accounting.settings.tabs.purchase_forms') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#sales-forms-tab">
                            <i class="ph-receipt me-1"></i>
                            {{ trans('accounting::accounting.settings.tabs.sales_forms') }}
                        </a>
                    </li>
                </ul>
                
                <!-- Tabs Content -->
                <div class="tab-content">
                    <!-- تنظیمات عمومی -->
                    <div class="tab-pane fade show active" id="general-tab">
                        <h5 class="text-primary mb-3">
                            <i class="ph-gear me-1"></i>
                            تنظیمات عمومی
                        </h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">تعداد اعشار</label>
                                <input type="number" class="form-control" name="decimal_places" 
                                       value="{{ old('decimal_places', $settings['decimal_places']) }}" 
                                       min="0" max="4">
                                <small class="text-muted">تعداد رقم اعشار در محاسبات مالی (0 تا 4)</small>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <h6 class="text-secondary mb-3">
                                    <i class="ph-wallet me-1"></i>
                                    حساب‌های سیستم
                                </h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">حساب‌های دریافتنی (Accounts Receivable)</label>
                                <select class="form-select enhanced-select" name="accounts_receivable_account_code" data-account-setting-tag="assets.accounts_receivable">
                                    <option value="">-- استفاده از کد استاندارد (1-120) --</option>
                                    @foreach($accounts->where('account_type', 'asset') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('accounts_receivable_account_code', $settings['accounts_receivable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">حساب پیش‌فرض برای مشتریان (کد استاندارد: 1-120)</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">حساب‌های پرداختنی (Accounts Payable)</label>
                                <select class="form-select enhanced-select" name="accounts_payable_account_code" data-account-setting-tag="liabilities.accounts_payable">
                                    <option value="">-- استفاده از کد استاندارد (2-210) --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}" 
                                            {{ old('accounts_payable_account_code', $settings['accounts_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">حساب پیش‌فرض برای تامین‌کنندگان (کد استاندارد: 2-210)</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">حساب واسط چک دریافتی</label>
                                <select class="form-select enhanced-select" name="cheques_receivable_clearing_account_code" data-account-setting-tag="assets.cheques_receivable_clearing">
                                    <option value="">-- استفاده از کد استاندارد (1-125) --</option>
                                    @foreach($accounts->where('account_type', 'asset') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('cheques_receivable_clearing_account_code', $settings['cheques_receivable_clearing_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">برای مرحله اول ثبت چک دریافتی استفاده می‌شود.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">حساب واسط چک پرداختی</label>
                                <select class="form-select enhanced-select" name="cheques_payable_clearing_account_code" data-account-setting-tag="liabilities.cheques_payable_clearing">
                                    <option value="">-- استفاده از کد استاندارد (2-215) --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('cheques_payable_clearing_account_code', $settings['cheques_payable_clearing_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">برای مرحله اول ثبت چک پرداختی استفاده می‌شود.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.treasury_bank_parent_account_code') }}</label>
                                <select class="form-select enhanced-select @error('treasury_bank_parent_account_code') is-invalid @enderror"
                                        name="treasury_bank_parent_account_code"
                                        data-account-setting-tag="treasury.bank_parent_account_code">
                                    <option value="">{{ trans('accounting::accounting.settings.hints.treasury_parent_account_placeholder') }}</option>
                                    @foreach($accounts->where('account_type', 'asset') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('treasury_bank_parent_account_code', $settings['treasury_bank_parent_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('treasury_bank_parent_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.treasury_bank_parent_account_code') }}</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.treasury_cashbox_parent_account_code') }}</label>
                                <select class="form-select enhanced-select @error('treasury_cashbox_parent_account_code') is-invalid @enderror"
                                        name="treasury_cashbox_parent_account_code"
                                        data-account-setting-tag="treasury.cashbox_parent_account_code">
                                    <option value="">{{ trans('accounting::accounting.settings.hints.treasury_parent_account_placeholder') }}</option>
                                    @foreach($accounts->where('account_type', 'asset') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('treasury_cashbox_parent_account_code', $settings['treasury_cashbox_parent_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('treasury_cashbox_parent_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.treasury_cashbox_parent_account_code') }}</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.fx_settlement_mode') }}</label>
                                <select class="form-select" name="fx_settlement_mode" id="fx-settlement-mode">
                                    <option value="split_accounts" @selected(old('fx_settlement_mode', $settings['fx_settlement_mode'] ?? 'split_accounts') === 'split_accounts')>
                                        {{ trans('accounting::accounting.settings.options.fx_settlement_split') }}
                                    </option>
                                    <option value="single_account" @selected(old('fx_settlement_mode', $settings['fx_settlement_mode'] ?? 'split_accounts') === 'single_account')>
                                        {{ trans('accounting::accounting.settings.options.fx_settlement_single') }}
                                    </option>
                                </select>
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.fx_settlement_mode') }}</small>
                                @error('fx_settlement_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.fx_difference_account') }} (FX Difference)</label>
                                <select class="form-select enhanced-select" name="fx_difference_account_code">
                                    <option value="">{{ trans('accounting::accounting.settings.hints.fx_difference_default') }}</option>
                                    @foreach($accounts->whereIn('account_type', ['expense', 'income', 'revenue']) as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('fx_difference_account_code', $settings['fx_difference_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.fx_difference') }}</small>
                                @error('fx_difference_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.fx_gain_account') }} (FX Gain)</label>
                                <select class="form-select enhanced-select" name="fx_gain_account_code">
                                    <option value="">{{ trans('accounting::accounting.settings.hints.fx_gain_default') }}</option>
                                    @foreach($accounts->whereIn('account_type', ['revenue', 'income']) as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('fx_gain_account_code', $settings['fx_gain_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.fx_gain') }}</small>
                                @error('fx_gain_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.fx_loss_account') }} (FX Loss)</label>
                                <select class="form-select enhanced-select" name="fx_loss_account_code">
                                    <option value="">{{ trans('accounting::accounting.settings.hints.fx_loss_default') }}</option>
                                    @foreach($accounts->where('account_type', 'expense') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('fx_loss_account_code', $settings['fx_loss_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ trans('accounting::accounting.settings.hints.fx_loss') }}</small>
                                @error('fx_loss_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12 mt-2"><hr class="my-2"></div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.minimum_wage') }}</label>
                                <input type="number" min="0" step="0.01" class="form-control" name="payroll_minimum_wage" value="{{ old('payroll_minimum_wage', $settings['payroll_minimum_wage'] ?? 0) }}">
                                <small class="text-muted">{{ trans('accounting::accounting.settings_payroll.minimum_wage_hint') }}</small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="payroll_attendance_feature_enabled" id="payroll-attendance-feature-enabled" value="1" {{ old('payroll_attendance_feature_enabled', !empty($settings['payroll_attendance_feature_enabled'])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="payroll-attendance-feature-enabled">
                                        {{ trans('accounting::accounting.attendance.settings.feature_enabled') }}
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.wages_payable') }}</label>
                                <select class="form-select enhanced-select" name="wages_payable_account_code" data-account-setting-tag="liabilities.wages_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('wages_payable_account_code', $settings['wages_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.social_insurance_payable') }}</label>
                                <select class="form-select enhanced-select" name="social_insurance_payable_account_code" data-account-setting-tag="liabilities.social_insurance_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('social_insurance_payable_account_code', $settings['social_insurance_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.employee_insurance_payable') }}</label>
                                <select class="form-select enhanced-select" name="employee_insurance_payable_account_code" data-account-setting-tag="liabilities.employee_insurance_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('employee_insurance_payable_account_code', $settings['employee_insurance_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.employer_insurance_payable') }}</label>
                                <select class="form-select enhanced-select" name="employer_insurance_payable_account_code" data-account-setting-tag="liabilities.employer_insurance_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('employer_insurance_payable_account_code', $settings['employer_insurance_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.payroll_tax_payable') }}</label>
                                <select class="form-select enhanced-select" name="payroll_tax_payable_account_code" data-account-setting-tag="liabilities.payroll_tax_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('payroll_tax_payable_account_code', $settings['payroll_tax_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.other_payroll_deductions_payable') }}</label>
                                <select class="form-select enhanced-select" name="other_payroll_deductions_payable_account_code" data-account-setting-tag="liabilities.other_payroll_deductions_payable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('other_payroll_deductions_payable_account_code', $settings['other_payroll_deductions_payable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.payroll_seniority_reserve') }}</label>
                                <select class="form-select enhanced-select" name="payroll_seniority_reserve_account_code" data-account-setting-tag="liabilities.payroll_seniority_reserve">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'liability') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('payroll_seniority_reserve_account_code', $settings['payroll_seniority_reserve_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.employer_social_insurance_expense') }}</label>
                                <select class="form-select enhanced-select" name="employer_social_insurance_account_code" data-account-setting-tag="expenses.employer_social_insurance">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'expense') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('employer_social_insurance_account_code', $settings['employer_social_insurance_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.payroll_seniority_expense') }}</label>
                                <select class="form-select enhanced-select" name="payroll_seniority_account_code" data-account-setting-tag="expenses.payroll_seniority">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'expense') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('payroll_seniority_account_code', $settings['payroll_seniority_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.employee_loans_receivable') }}</label>
                                <select class="form-select enhanced-select" name="employee_loans_receivable_account_code" data-account-setting-tag="assets.employee_loans_receivable">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'asset') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('employee_loans_receivable_account_code', $settings['employee_loans_receivable_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.employee_loan_interest_income') }}</label>
                                <select class="form-select enhanced-select" name="employee_loan_interest_income_account_code" data-account-setting-tag="revenue.employee_loan_interest_income">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'income') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('employee_loan_interest_income_account_code', $settings['employee_loan_interest_income_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.equity_capital') }}</label>
                                <select class="form-select enhanced-select" name="equity_capital_account_code">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'equity') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('equity_capital_account_code', $settings['equity_capital_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.retained_earnings_account') }}</label>
                                <select class="form-select enhanced-select @error('retained_earnings_account_code') is-invalid @enderror"
                                        name="retained_earnings_account_code"
                                        data-account-setting-tag="equity.retained_earnings">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'equity') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('retained_earnings_account_code', $settings['retained_earnings_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('retained_earnings_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">کد پیشنهادی: <strong>3200</strong> (سود/زیان انباشته)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings.fields.income_summary_account') }}</label>
                                <select class="form-select enhanced-select @error('income_summary_account_code') is-invalid @enderror"
                                        name="income_summary_account_code"
                                        data-account-setting-tag="equity.income_summary">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'equity') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('income_summary_account_code', $settings['income_summary_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('income_summary_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">کد پیشنهادی: <strong>3900</strong> (خلاصه سود و زیان)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_payroll.shareholder_drawings') }}</label>
                                <select class="form-select enhanced-select" name="shareholder_drawings_account_code">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'equity') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('shareholder_drawings_account_code', $settings['shareholder_drawings_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- تنظیمات تطبیق بانک -->
                    <div class="tab-pane fade" id="bank-reconciliation-tab">
                        <h5 class="text-primary mb-3">
                            <i class="ph-arrows-clockwise me-1"></i>
                            {{ trans('accounting::accounting.settings_bank_reconciliation.section_title') }}
                        </h5>

                        <div class="alert alert-info">
                            <i class="ph-info me-2"></i>
                            {{ trans('accounting::accounting.settings_bank_reconciliation.section_hint') }}
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_bank_reconciliation.bank_interest_income') }}</label>
                                <select class="form-select enhanced-select @error('bank_interest_income_account_code') is-invalid @enderror" name="bank_interest_income_account_code" data-account-setting-tag="revenue.bank_interest_income">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->whereIn('account_type', ['income', 'revenue']) as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('bank_interest_income_account_code', $settings['bank_interest_income_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('bank_interest_income_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ trans('accounting::accounting.settings_bank_reconciliation.bank_charges') }}</label>
                                <select class="form-select enhanced-select @error('bank_charges_account_code') is-invalid @enderror" name="bank_charges_account_code" data-account-setting-tag="expenses.bank_charges">
                                    <option value="">-- {{ trans('accounting::accounting.common.none') }} --</option>
                                    @foreach($accounts->where('account_type', 'expense') as $account)
                                    <option value="{{ $account->code }}"
                                            {{ old('bank_charges_account_code', $settings['bank_charges_account_code'] ?? '') == $account->code ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('bank_charges_account_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- تنظیمات مالیاتی -->
                    <div class="tab-pane fade" id="tax-tab">
                        <!-- مالیات بر ارزش افزوده -->
                        <div class="border rounded p-3 mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="ph-shopping-cart me-1"></i>
                                مالیات بر ارزش افزوده (VAT)
                            </h5>

                            @php
                                $vatRateManagedByTaxRates = (bool) ($settings['vat_rate_managed_by_tax_rates'] ?? false);
                                $vatRateTaxRatesUrl = (string) ($settings['vat_rate_tax_rates_url'] ?? '');
                            @endphp

                            @if($vatRateManagedByTaxRates)
                                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <span>{{ trans('accounting::accounting.settings.hints.vat_rate_source_tax_rates') }}</span>
                                    @if($vatRateTaxRatesUrl !== '')
                                        <a href="{{ $vatRateTaxRatesUrl }}" class="btn btn-sm btn-outline-primary">
                                            {{ trans('accounting::accounting.settings.actions.open_tax_rates') }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="vat_enabled" 
                                               id="vat_enabled" value="1" 
                                               {{ old('vat_enabled', $settings['vat_enabled']) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="vat_enabled">
                                            فعال‌سازی مالیات بر ارزش افزوده
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">نرخ استاندارد (%)</label>
                                    <input type="number"
                                           step="0.01"
                                           class="form-control"
                                           value="{{ old('vat_rate', $settings['vat_rate']) }}"
                                           @if($vatRateManagedByTaxRates) disabled readonly @endif>
                                    <small class="text-muted">
                                        @if($vatRateManagedByTaxRates)
                                            {{ trans('accounting::accounting.settings.hints.vat_rate_source_tax_rates') }}
                                        @else
                                            {{ trans('accounting::accounting.settings.hints.vat_rate') }}
                                        @endif
                                    </small>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">نرخ کاهش یافته (%)</label>
                                    <input type="number" step="0.01" class="form-control" 
                                           name="vat_rate_reduced" value="{{ old('vat_rate_reduced', $settings['vat_rate_reduced']) }}">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">نرخ معاف (%)</label>
                                    <input type="number" step="0.01" class="form-control" 
                                           name="vat_rate_zero" value="{{ old('vat_rate_zero', $settings['vat_rate_zero']) }}">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">روش محاسبه</label>
                                    <select class="form-select" name="tax_calculation_method">
                                        <option value="exclusive" {{ old('tax_calculation_method', $settings['tax_calculation_method']) == 'exclusive' ? 'selected' : '' }}>
                                            جدا از قیمت
                                        </option>
                                        <option value="inclusive" {{ old('tax_calculation_method', $settings['tax_calculation_method']) == 'inclusive' ? 'selected' : '' }}>
                                            شامل قیمت
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">حساب مالیات پرداختنی (فروش)</label>
                                    <select class="form-select @error('vat_account_payable_id') is-invalid @enderror" name="vat_account_payable_id">
                                        <option value="">-- انتخاب --</option>
                                        @foreach($accounts->where('account_type', 'liability') as $account)
                                        <option value="{{ $account->id }}" 
                                                {{ old('vat_account_payable_id', $settings['vat_account_payable_id']) == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('vat_account_payable_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">حساب مالیات دریافتنی (خرید)</label>
                                    <select class="form-select @error('vat_account_receivable_id') is-invalid @enderror" name="vat_account_receivable_id" data-account-setting-tag="vat.account_receivable_id">
                                        <option value="">-- انتخاب --</option>
                                        @foreach($accounts->where('account_type', 'asset') as $account)
                                        <option value="{{ $account->id }}" 
                                                {{ old('vat_account_receivable_id', $settings['vat_account_receivable_id']) == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('vat_account_receivable_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="text-muted">کد پیشنهادی استاندارد: <strong>1105</strong> (مالیات بر ارزش افزوده دریافتنی)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- مالیات بر درآمد -->
                        <div class="border rounded p-3 mb-4">
                            <h5 class="text-success mb-3">
                                <i class="ph-coin me-1"></i>
                                مالیات بر درآمد (Corporate Income Tax)
                            </h5>
                            <div class="alert alert-info mb-3">
                                <i class="ph-info me-2"></i>
                                <strong>توجه:</strong> مالیات بر درآمد در پایان سال مالی از صورت سود و زیان محاسبه می‌شود، نه در هر فاکتور.
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="income_tax_enabled" 
                                               id="income_tax_enabled" value="1" 
                                               {{ old('income_tax_enabled', $settings['income_tax_enabled']) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="income_tax_enabled">
                                            فعال‌سازی مالیات بر درآمد
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">نرخ مالیات (%)</label>
                                    <input type="number" step="0.01" class="form-control" 
                                           name="income_tax_rate" value="{{ old('income_tax_rate', $settings['income_tax_rate']) }}">
                                    <small class="text-muted">استاندارد جهانی: 20-30%، ایران: 25%</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">حساب هزینه مالیات بر درآمد</label>
                                    <select class="form-select @error('income_tax_expense_account_id') is-invalid @enderror" name="income_tax_expense_account_id">
                                        <option value="">-- انتخاب --</option>
                                        @foreach($accounts->where('account_type', 'expense') as $account)
                                        <option value="{{ $account->id }}" 
                                                {{ old('income_tax_expense_account_id', $settings['income_tax_expense_account_id']) == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('income_tax_expense_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="text-muted">حساب بدهکار (Expense)</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">حساب مالیات بر درآمد پرداختنی</label>
                                    <select class="form-select @error('income_tax_payable_account_id') is-invalid @enderror" name="income_tax_payable_account_id">
                                        <option value="">-- انتخاب --</option>
                                        @foreach($accounts->where('account_type', 'liability') as $account)
                                        <option value="{{ $account->id }}" 
                                                {{ old('income_tax_payable_account_id', $settings['income_tax_payable_account_id']) == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('income_tax_payable_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="text-muted">حساب بستانکار (Liability)</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">روش گرد کردن</label>
                                    <select class="form-select" name="tax_rounding">
                                        <option value="round" {{ old('tax_rounding', $settings['tax_rounding']) == 'round' ? 'selected' : '' }}>
                                            عادی (Round)
                                        </option>
                                        <option value="ceil" {{ old('tax_rounding', $settings['tax_rounding']) == 'ceil' ? 'selected' : '' }}>
                                            به بالا (Ceiling)
                                        </option>
                                        <option value="floor" {{ old('tax_rounding', $settings['tax_rounding']) == 'floor' ? 'selected' : '' }}>
                                            به پایین (Floor)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- تنظیمات ارز -->
                    <div class="tab-pane fade" id="currency-tab">
                        <h5 class="text-warning mb-3">
                            <i class="ph-currency-circle-dollar me-1"></i>
                            تنظیمات ارزی
                        </h5>

                        <div class="alert alert-info">
                            <i class="ph-info me-2"></i>
                            {{ trans('accounting::accounting.settings.hints.fx_accounts_intro') }}
                        </div>

                        @include('accounting::components.collapse_help_card', [
                            'collapseId' => 'accounting-settings-fx-help',
                            'toggleLabel' => trans('accounting::accounting.settings.fx_help.toggle_label'),
                            'title' => trans('accounting::accounting.settings.fx_help.title'),
                            'paragraphs' => [
                                trans('accounting::accounting.settings.fx_help.p1'),
                                trans('accounting::accounting.settings.fx_help.p2'),
                                trans('accounting::accounting.settings.fx_help.p3'),
                                trans('accounting::accounting.settings.fx_help.p4'),
                            ],
                        ])
                    </div>

                    <div class="tab-pane fade" id="purchase-forms-tab">
                        @php
                            $purchaseForms = (array) ($settings['purchase_forms'] ?? []);
                            $hiddenFields = (array) old('accounting_purchase_hidden_fields', (array) ($purchaseForms['hidden_fields'] ?? []));
                            $purchaseFormFieldOptions = [
                                'purchase_order_status' => trans('accounting::accounting.settings.purchase_forms.fields.purchase_order_status'),
                                'purchase_order_notes' => trans('accounting::accounting.settings.purchase_forms.fields.purchase_order_notes'),
                                'supplier_invoice_status' => trans('accounting::accounting.settings.purchase_forms.fields.supplier_invoice_status'),
                                'supplier_payment_status' => trans('accounting::accounting.settings.purchase_forms.fields.supplier_payment_status'),
                                'supplier_payment_notes' => trans('accounting::accounting.settings.purchase_forms.fields.supplier_payment_notes'),
                            ];
                        @endphp

                        <div class="border rounded p-3">
                            <h5 class="text-info mb-3">
                                <i class="ph-shopping-cart-simple me-1"></i>
                                {{ trans('accounting::accounting.settings.purchase_forms.section_title') }}
                            </h5>
                            <p class="text-muted mb-3">{{ trans('accounting::accounting.settings.purchase_forms.hint') }}</p>
                            <div class="row g-2">
                                @foreach($purchaseFormFieldOptions as $fieldKey => $label)
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   id="accounting_purchase_hidden_{{ $fieldKey }}"
                                                   name="accounting_purchase_hidden_fields[]"
                                                   value="{{ $fieldKey }}"
                                                   @checked(in_array($fieldKey, $hiddenFields, true))>
                                            <label class="form-check-label" for="accounting_purchase_hidden_{{ $fieldKey }}">
                                                {{ $label }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="sales-forms-tab">
                        @php
                            $salesForms = (array) ($settings['sales_forms'] ?? []);
                            $salesHiddenFields = (array) old('accounting_sales_hidden_fields', (array) ($salesForms['hidden_fields'] ?? []));
                            $salesDefaultCustomerId = (int) old('sales_default_customer_id', (int) ($settings['sales_default_customer_id'] ?? 0));
                            $salesDefaultCustomerOptions = (array) ($settings['sales_default_customer_options'] ?? []);
                            $salesFormFieldOptions = [
                                'customer_invoice_status' => trans('accounting::accounting.settings.sales_forms.fields.customer_invoice_status'),
                                'customer_invoice_tax_amount' => trans('accounting::accounting.settings.sales_forms.fields.customer_invoice_tax_amount'),
                                'customer_invoice_discount_amount' => trans('accounting::accounting.settings.sales_forms.fields.customer_invoice_discount_amount'),
                            ];
                        @endphp

                        <div class="border rounded p-3">
                            <h5 class="text-info mb-3">
                                <i class="ph-receipt me-1"></i>
                                {{ trans('accounting::accounting.settings.sales_forms.section_title') }}
                            </h5>
                            <p class="text-muted mb-3">{{ trans('accounting::accounting.settings.sales_forms.hint') }}</p>

                            @php
                                $generalCustomerExists = (bool) ($settings['sales_general_customer_exists'] ?? false);
                                $generalCustomer = is_array($settings['sales_general_customer'] ?? null)
                                    ? (array) $settings['sales_general_customer']
                                    : null;
                            @endphp
                            <div class="alert d-flex flex-wrap justify-content-between align-items-center gap-2 {{ $generalCustomerExists ? 'alert-success' : 'alert-warning' }}" id="general-customer-warning">
                                <span id="general-customer-warning-text">
                                    @if($generalCustomerExists && $generalCustomer)
                                        {{ trans('accounting::accounting.settings.sales_forms.hints.general_customer_ready', [
                                            'name' => (string) ($generalCustomer['name'] ?? '—'),
                                            'id' => (int) ($generalCustomer['id'] ?? 0),
                                        ]) }}
                                    @else
                                        {{ trans('accounting::accounting.settings.sales_forms.hints.general_customer_missing') }}
                                    @endif
                                </span>
                                <button type="button"
                                        class="btn btn-sm {{ $generalCustomerExists ? 'btn-outline-success' : 'btn-warning' }}"
                                        id="create-general-customer-btn"
                                        data-route="{{ $createDefaultSalesCustomerRoute ?? (\Illuminate\Support\Facades\Route::has('admin.accounting.settings.create-default-sales-customer') ? route('admin.accounting.settings.create-default-sales-customer') : '') }}">
                                    {{ trans('accounting::accounting.settings.sales_forms.actions.create_general_customer') }}
                                </button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ trans('accounting::accounting.settings.sales_forms.fields.default_customer_id') }}</label>
                                <select class="form-select enhanced-select @error('sales_default_customer_id') is-invalid @enderror" name="sales_default_customer_id" id="sales-default-customer-id">
                                    <option value="0">{{ trans('accounting::accounting.settings.sales_forms.options.auto_general_customer') }}</option>
                                    @foreach($salesDefaultCustomerOptions as $customerOption)
                                        <option value="{{ (int) ($customerOption['id'] ?? 0) }}" @selected($salesDefaultCustomerId === (int) ($customerOption['id'] ?? 0))>
                                            {{ (string) ($customerOption['label'] ?? ('#'.(int) ($customerOption['id'] ?? 0))) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sales_default_customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">{{ trans('accounting::accounting.settings.sales_forms.hints.default_customer_id') }}</small>
                            </div>
                            <div class="row g-2">
                                @foreach($salesFormFieldOptions as $fieldKey => $label)
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   id="accounting_sales_hidden_{{ $fieldKey }}"
                                                   name="accounting_sales_hidden_fields[]"
                                                   value="{{ $fieldKey }}"
                                                   @checked(in_array($fieldKey, $salesHiddenFields, true))>
                                            <label class="form-check-label" for="accounting_sales_hidden_{{ $fieldKey }}">
                                                {{ $label }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $guideParagraphs = [
                        trans('accounting::accounting.settings.guide.general.title') . ': ' .
                            trans('accounting::accounting.settings.guide.general.items.default_currency') . ' ' .
                            trans('accounting::accounting.settings.guide.general.items.decimal_places') . ' ' .
                            trans('accounting::accounting.settings.guide.general.items.accounts_payable'),
                        trans('accounting::accounting.settings.guide.tax.title') . ': ' .
                            trans('accounting::accounting.settings.guide.tax.items.vat') . ' ' .
                            trans('accounting::accounting.settings.guide.tax.items.income_tax') . ' ' .
                            trans('accounting::accounting.settings.guide.tax.items.rounding'),
                        trans('accounting::accounting.settings.guide.currency.title') . ': ' .
                            trans('accounting::accounting.settings.guide.currency.items.consistency') . ' ' .
                            trans('accounting::accounting.settings.guide.currency.items.quick_links'),
                        trans('accounting::accounting.settings.guide.purchase_forms.title') . ': ' .
                            trans('accounting::accounting.settings.guide.purchase_forms.items.scope') . ' ' .
                            trans('accounting::accounting.settings.guide.purchase_forms.items.hidden_fields') . ' ' .
                            trans('accounting::accounting.settings.guide.purchase_forms.items.defaults'),
                        trans('accounting::accounting.settings.guide.sales_forms.title') . ': ' .
                            trans('accounting::accounting.settings.guide.sales_forms.items.scope') . ' ' .
                            trans('accounting::accounting.settings.guide.sales_forms.items.hidden_fields') . ' ' .
                            trans('accounting::accounting.settings.guide.sales_forms.items.defaults'),
                    ];
                @endphp
                @include('accounting::components.collapse_help_card', [
                    'collapseId' => 'accounting-settings-tabs-guide',
                    'toggleLabel' => trans('accounting::accounting.settings.guide.title'),
                    'title' => trans('accounting::accounting.settings.guide.intro'),
                    'paragraphs' => $guideParagraphs,
                    'cardClass' => 'mt-4',
                ])
                
                @if($errors->any())
                <div class="alert alert-danger mt-3">
                    <i class="ph-warning-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
                @endif
                @if(session('success'))
                <div class="alert alert-success mt-3">
                    <i class="ph-check-circle me-1"></i>
                    {{ session('success') }}
                </div>
                @endif
            </div>
            
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-1"></i>
                    ذخیره تنظیمات
                </button>
                <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-secondary">
                    <i class="ph-arrow-left me-1"></i>
                    بازگشت
                </a>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="accounting-settings-errors-json">@json($errors->toArray(), JSON_UNESCAPED_UNICODE)</script>
@endsection

@section('assets')
    <script src="{{ asset('vendor/accounting/admin/js/account-settings-focus.js') }}"></script>
    <script src="{{ asset('vendor/accounting/admin/js/account-settings-default-customer.js') }}"></script>
@endsection
