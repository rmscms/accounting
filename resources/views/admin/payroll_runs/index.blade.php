@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.payroll_runs.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.payroll_runs.title') }}</h4>
        <a href="{{ route('admin.accounting.payroll-runs.create') }}" class="btn btn-primary">
            {{ trans('accounting::accounting.payroll_runs.create') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.run_number') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.title') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.period') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.total_gross') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.total_net') }}</th>
                        <th>{{ trans('accounting::accounting.payroll_runs.columns.status') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.payroll_runs.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->run_number }}</td>
                            <td>{{ $run->title }}</td>
                            <td>{{ $run->period_start?->format('Y-m-d') }} — {{ $run->period_end?->format('Y-m-d') }}</td>
                            <td>{{ number_format((float) $run->total_gross, 0) }}</td>
                            <td>{{ number_format((float) $run->total_net, 0) }}</td>
                            <td>{{ trans('accounting::accounting.payroll_runs.statuses.'.$run->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.accounting.payroll-runs.show', $run->id) }}" class="btn btn-sm btn-outline-primary">
                                    {{ trans('accounting::accounting.payroll_runs.actions.view') }}
                                </a>
                                @if($run->status === \RMS\Accounting\Models\PayrollRun::STATUS_DRAFT)
                                    <a href="{{ route('admin.accounting.payroll-runs.edit', $run->id) }}" class="btn btn-sm btn-outline-secondary">
                                        {{ trans('accounting::accounting.payroll_runs.actions.edit') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ trans('accounting::accounting.payroll_runs.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $runs->links() }}
        </div>
    </div>
</div>
@endsection
