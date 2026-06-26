@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.payroll_insurance.page_title'))
@section('content')
@php
    $journalDispPayroll = old('journal_date');
    $journalDispPayroll = ($journalDispPayroll !== null && trim((string) $journalDispPayroll) !== '')
        ? trim(\RMS\Helper\changeNumberToEn((string) $journalDispPayroll))
        : \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
@endphp
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.payroll_insurance.page_title') }}</h4>
        @include('accounting::components.account_settings_link', [
            'label' => trans('accounting::accounting.payroll_insurance.go_to_settings'),
            'class' => 'btn btn-outline-secondary btn-sm',
        ])
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if($errors->has('payroll_insurance'))
        <div class="alert alert-danger">{{ $errors->first('payroll_insurance') }}</div>
    @endif
    @if(!empty($missingMappings))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">{{ trans('accounting::accounting.payroll_insurance.missing_settings_title') }}</div>
            <ul class="mb-0 ps-3">
                @foreach($missingMappings as $missing)
                    <li>
                        {{ (string) ($missing['label'] ?? '') }} — {{ (string) ($missing['message'] ?? '') }}
                        @include('accounting::components.account_settings_link', [
                            'tag' => (string) ($missing['tag'] ?? ''),
                            'label' => trans('accounting::accounting.payroll_insurance.fix_this_setting'),
                            'class' => 'btn btn-link btn-sm p-0 ms-1 align-baseline',
                        ])
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('accounting::components.collapse_help_card', [
        'toggleLabel' => trans('accounting::accounting.payroll_insurance.help_toggle'),
        'paragraphs' => [
            trans('accounting::accounting.payroll_insurance.help_p1'),
            trans('accounting::accounting.payroll_insurance.help_p2'),
            trans('accounting::accounting.payroll_insurance.help_p3'),
        ],
        'body_html' => '<p class="mb-0 small text-muted">'.e(trans('accounting::accounting.payroll_insurance.chart_note')).'</p>',
    ])

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-primary border-opacity-25">
                <div class="card-header">{{ trans('accounting::accounting.payroll_insurance.form_accrual_title') }}</div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.accounting.payroll-insurance.accrual') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.amount_label') }}</label>
                            <input type="text" inputmode="decimal" autocomplete="off" name="amount" class="form-control js-accounting-amount-input" value="{{ old('amount') }}" required>
                            <small class="text-muted">{{ trans('accounting::accounting.payroll_insurance.amount_hint') }}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.employee_label') }}</label>
                            <select name="employee_id" class="form-select">
                                <option value="">{{ trans('accounting::accounting.payroll_insurance.employee_placeholder') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ trans('accounting::accounting.payroll_insurance.employee_hint') }}</small>
                        </div>
                        <x-accounting::date-field
                            name="journal_date"
                            :label="trans('accounting::accounting.payroll_insurance.journal_date_label')"
                            :value="$journalDispPayroll"
                            :required="true"
                            col-class="col-12"
                        />
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.description_label') }}</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.payroll_insurance.submit_accrual') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-warning border-opacity-25">
                <div class="card-header">{{ trans('accounting::accounting.payroll_insurance.form_payment_title') }}</div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.accounting.payroll-insurance.payment') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.amount_label') }}</label>
                            <input type="text" inputmode="decimal" autocomplete="off" name="amount" class="form-control js-accounting-amount-input" value="{{ old('amount') }}" required>
                            <small class="text-muted">{{ trans('accounting::accounting.payroll_insurance.amount_hint') }}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.employee_label') }}</label>
                            <select name="employee_id" class="form-select">
                                <option value="">{{ trans('accounting::accounting.payroll_insurance.employee_placeholder') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ trans('accounting::accounting.payroll_insurance.employee_hint') }}</small>
                        </div>
                        <x-accounting::date-field
                            name="journal_date"
                            :label="trans('accounting::accounting.payroll_insurance.journal_date_label')"
                            :value="$journalDispPayroll"
                            :required="true"
                            col-class="col-12"
                        />
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.bank_label') }}</label>
                            <select name="bank_id" class="form-select" required>
                                <option value="">{{ trans('accounting::accounting.payroll_insurance.bank_placeholder') }}</option>
                                @foreach($banks as $b)
                                    <option value="{{ $b->id }}" @selected((int) old('bank_id') === (int) $b->id)>{{ $b->label_for_select }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.description_label') }}</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="mark_as_settled" name="mark_as_settled" @checked(old('mark_as_settled', '1') === '1')>
                                <label class="form-check-label" for="mark_as_settled">
                                    {{ trans('accounting::accounting.payroll_insurance.mark_as_settled_label') }}
                                </label>
                            </div>
                            <small class="text-muted">{{ trans('accounting::accounting.payroll_insurance.mark_as_settled_hint') }}</small>
                        </div>
                        @php
                            $attachmentMimeJson = json_encode(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
                            $attachmentMaxSizeAttr = max(0.1, round(($attachmentMaxKb ?? 10240) / 1024, 1)) . 'MB';
                        @endphp
                        <div class="mb-3">
                            <label class="form-label">{{ trans('accounting::accounting.payroll_insurance.attachments_label') }}</label>
                            <input type="file"
                                   name="attachments[]"
                                   class="form-control"
                                   multiple
                                   data-max-size="{{ $attachmentMaxSizeAttr }}"
                                   data-allowed-types="{{ e($attachmentMimeJson) }}"
                                   data-formats-hint="{{ trans('accounting::accounting.attachments.formats_hint') }}"
                                   accept="image/jpeg,image/png,image/webp,application/pdf,.pdf">
                            <small class="text-muted">
                                {{ trans('accounting::accounting.payroll_insurance.attachments_help', ['max' => $attachmentMaxKb ?? 10240, 'maxn' => $attachmentMaxPerPayment ?? 5]) }}
                            </small>
                        </div>
                        <button type="submit" class="btn btn-warning text-dark">{{ trans('accounting::accounting.payroll_insurance.submit_payment') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 shadow-sm border-info border-opacity-25">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ trans('accounting::accounting.payroll_insurance.settlements_title') }}</span>
            <span class="badge bg-light text-dark">{{ $settlements->count() }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_date') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_amount') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_bank') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_employee') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_status') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_journal') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_attachments') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_insurance.col_actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td>{{ \RMS\Helper\persian_date(\Carbon\Carbon::parse((string) $settlement->journal_date), 'Y/m/d') }}</td>
                            <td class="text-end">{{ number_format((float) $settlement->amount) }}</td>
                            <td>{{ $settlement->bank?->name ?? '-' }}</td>
                            <td>{{ $settlement->employee?->name ?? '-' }}</td>
                            <td>
                                @if((string) $settlement->status === \RMS\Accounting\Models\PayrollInsuranceSettlement::STATUS_SETTLED)
                                    <span class="badge bg-success">{{ trans('accounting::accounting.payroll_insurance.status_settled') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ trans('accounting::accounting.payroll_insurance.status_open') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($settlement->manualJournal)
                                    <a href="{{ route('admin.accounting.manual-journals.show', $settlement->manualJournal->id) }}" target="_blank" rel="noopener">
                                        {{ $settlement->manualJournal->journal_number }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @forelse($settlement->attachments as $att)
                                    <a href="{{ route('admin.accounting.attachments.download', $att->uuid) }}" class="btn btn-sm btn-outline-primary me-1 mb-1" target="_blank" rel="noopener">
                                        {{ \Illuminate\Support\Str::limit($att->original_name, 24) }}
                                    </a>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                            <td>
                                @if((string) $settlement->status !== \RMS\Accounting\Models\PayrollInsuranceSettlement::STATUS_SETTLED)
                                    <form method="post" action="{{ route('admin.accounting.payroll-insurance.settlements.close', $settlement->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ trans('accounting::accounting.payroll_insurance.close_settlement_btn') }}</button>
                                    </form>
                                @else
                                    <span class="text-muted small">{{ trans('accounting::accounting.payroll_insurance.closed_at') }}: {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse((string) $settlement->settled_at), 'Y/m/d H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ trans('accounting::accounting.payroll_insurance.no_settlements') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
