@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.attendance.period_title'))
@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">
                    <i class="ph-clock-countdown me-1"></i>
                    {{ trans('accounting::accounting.attendance.period_title') }}
                </h4>
                <p class="text-muted mb-0">
                    {{ $periodStartDisplay }} - {{ $periodEndDisplay }}
                    <span class="badge bg-primary ms-2">{{ $period->status }}</span>
                </p>
            </div>
            <a href="{{ route('admin.accounting.attendance-worklogs.index') }}" class="btn btn-secondary">
                {{ trans('accounting::accounting.common.back') }}
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <form method="post" action="{{ route('admin.accounting.attendance-worklogs.submit', $period->id) }}">
                    @csrf
                    <button class="btn btn-outline-primary" type="submit">{{ trans('accounting::accounting.attendance.actions.submit') }}</button>
                </form>
                <form method="post" action="{{ route('admin.accounting.attendance-worklogs.supervisor-approve', $period->id) }}">
                    @csrf
                    <button class="btn btn-outline-info" type="submit">{{ trans('accounting::accounting.attendance.actions.supervisor_approve') }}</button>
                </form>
                <form method="post" action="{{ route('admin.accounting.attendance-worklogs.hr-approve', $period->id) }}">
                    @csrf
                    <button class="btn btn-outline-success" type="submit">{{ trans('accounting::accounting.attendance.actions.hr_approve') }}</button>
                </form>
                <form method="post" action="{{ route('admin.accounting.attendance-worklogs.lock', $period->id) }}" class="d-flex gap-2">
                    @csrf
                    <input name="reason" class="form-control" placeholder="{{ trans('accounting::accounting.attendance.lock_reason') }}">
                    <button class="btn btn-warning" type="submit">{{ trans('accounting::accounting.attendance.actions.lock') }}</button>
                </form>
                <form method="post" action="{{ route('admin.accounting.attendance-worklogs.unlock', $period->id) }}" class="d-flex gap-2">
                    @csrf
                    <input name="reason" class="form-control" placeholder="{{ trans('accounting::accounting.attendance.unlock_reason') }}">
                    <button class="btn btn-dark" type="submit">{{ trans('accounting::accounting.attendance.actions.unlock') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">{{ trans('accounting::accounting.attendance.manual_entry_title') }}</div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.accounting.attendance-worklogs.daily-upsert') }}">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                        <div class="mb-2">
                            <label class="form-label">{{ trans('accounting::accounting.attendance.fields.employee') }}</label>
                            <select name="employee_id" class="form-select enhanced-select" required>
                                <option value="">{{ trans('accounting::accounting.settings.options.select') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ trans('accounting::accounting.attendance.fields.work_date') }}</label>
                            <x-accounting::date-field name="work_date" value="" required="true" />
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.planned_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="planned_minutes" value="440">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.worked_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="worked_minutes" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.overtime_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="overtime_minutes" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.leave_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="leave_minutes" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.absence_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="absence_minutes" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ trans('accounting::accounting.attendance.fields.late_minutes') }}</label>
                                <input type="number" min="0" class="form-control" name="late_minutes" value="0">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label">{{ trans('accounting::accounting.attendance.fields.notes') }}</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" name="is_termination_final_day" id="termination-final-day">
                            <label class="form-check-label" for="termination-final-day">{{ trans('accounting::accounting.attendance.fields.termination_final_day') }}</label>
                        </div>
                        <button class="btn btn-primary mt-3" type="submit">{{ trans('accounting::accounting.payroll_runs.actions.save') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ trans('accounting::accounting.attendance.import_csv_title') }}</div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.accounting.attendance-worklogs.import-csv') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                        <div class="input-group">
                            <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                            <button class="btn btn-outline-primary" type="submit">{{ trans('accounting::accounting.attendance.actions.import_csv') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ trans('accounting::accounting.attendance.summary_title') }}</div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('accounting::accounting.attendance.fields.employee') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.attendance.summary.planned_days') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.attendance.summary.worked_days') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.attendance.summary.payable_days') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.attendance.summary.overtime_hours') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.attendance.summary.absence_days') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summaryRows as $row)
                                <tr>
                                    <td>{{ $row['employee_name'] }}</td>
                                    <td class="text-end">{{ number_format((float) $row['planned_days'], 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['worked_days'], 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['payable_days'], 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['overtime_hours'], 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['absence_days'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">{{ trans('accounting::accounting.attendance.empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
