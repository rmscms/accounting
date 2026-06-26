@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.attendance.page_title'))
@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">
                    <i class="ph-calendar-check me-1"></i>
                    {{ trans('accounting::accounting.attendance.page_title') }}
                </h4>
                <p class="text-muted mb-0">{{ trans('accounting::accounting.attendance.page_subtitle') }}</p>
            </div>
            <span class="badge {{ $featureEnabled ? 'bg-success' : 'bg-warning' }}">
                {{ $featureEnabled ? trans('accounting::accounting.attendance.feature_enabled') : trans('accounting::accounting.attendance.feature_disabled') }}
            </span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3">{{ trans('accounting::accounting.attendance.open_period_title') }}</h6>
            <form method="post" action="{{ route('admin.accounting.attendance-worklogs.open-period') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.period_start') }}</label>
                        <x-accounting::date-field
                            name="period_start"
                            value="{{ old('period_start', $defaultPeriodStartDisplay) }}"
                            required="true"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.period_end') }}</label>
                        <x-accounting::date-field
                            name="period_end"
                            value="{{ old('period_end', $defaultPeriodEndDisplay) }}"
                            required="true"
                        />
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-plus-circle me-1"></i>
                            {{ trans('accounting::accounting.attendance.actions.open_period') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.attendance.columns.period') }}</th>
                        <th>{{ trans('accounting::accounting.attendance.columns.status') }}</th>
                        <th>{{ trans('accounting::accounting.attendance.columns.policy') }}</th>
                        <th>{{ trans('accounting::accounting.attendance.columns.updated_at') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.attendance.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periods as $period)
                        <tr>
                            <td>
                                {{ \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay((string) $period->period_start?->format('Y-m-d')) }}
                                -
                                {{ \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay((string) $period->period_end?->format('Y-m-d')) }}
                            </td>
                            <td><span class="badge bg-primary">{{ $period->status }}</span></td>
                            <td>{{ optional($period->policyProfile)->title ?: '-' }}</td>
                            <td>{{ $period->updated_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.accounting.attendance-worklogs.show', $period->id) }}" class="btn btn-sm btn-outline-primary">
                                    {{ trans('accounting::accounting.payroll_runs.actions.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ trans('accounting::accounting.attendance.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $periods->links() }}
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'attendance-worklog-help',
        'cardClass' => 'mt-3',
        'toggleLabel' => trans('accounting::accounting.attendance.help_toggle'),
        'title' => trans('accounting::accounting.attendance.help_title'),
        'paragraphs' => [
            trans('accounting::accounting.attendance.help_intro'),
        ],
        'body_html' => '<ul class="mb-0 ps-3">'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_open_period')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_manual')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_csv')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_device')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_submit')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_supervisor')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_hr')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_lock')).'</li>'.
            '<li class="mb-2">'.e(trans('accounting::accounting.attendance.help_step_payroll')).'</li>'.
            '<li>'.e(trans('accounting::accounting.attendance.help_step_reports')).'</li>'.
        '</ul>',
    ])
</div>
@endsection
