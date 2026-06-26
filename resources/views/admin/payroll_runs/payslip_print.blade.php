@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.payroll_runs.print_title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0">{{ trans('accounting::accounting.payroll_runs.print_title') }}</h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-dark" onclick="window.print()">{{ trans('accounting::accounting.payroll_runs.actions.print') }}</button>
            <a href="{{ route('admin.accounting.payroll-runs.show', $run->id) }}" class="btn btn-light">{{ trans('accounting::accounting.common.back') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.run_number') }}</small>
                    <strong>{{ $run->run_number }}</strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.fields.employee') }}</small>
                    <strong>{{ $line->employee?->name }}</strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.payroll_runs.columns.period') }}</small>
                    <strong>{{ $run->period_start?->format('Y-m-d') }} - {{ $run->period_end?->format('Y-m-d') }}</strong>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ trans('accounting::accounting.payroll_runs.items.fields.type') }}</th>
                            <th>{{ trans('accounting::accounting.payroll_runs.items.fields.code') }}</th>
                            <th>{{ trans('accounting::accounting.payroll_runs.items.fields.title') }}</th>
                            <th>{{ trans('accounting::accounting.payroll_runs.items.fields.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($line->items as $item)
                            <tr>
                                <td>{{ trans('accounting::accounting.payroll_runs.items.types.'.$item->type) }}</td>
                                <td><code>{{ $item->code }}</code></td>
                                <td>{{ $item->title }}</td>
                                <td>{{ number_format((float) $item->amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">{{ trans('accounting::accounting.payroll_runs.fields.gross_salary') }}</th>
                            <th>{{ number_format((float) $line->gross_salary, 0) }}</th>
                        </tr>
                        <tr>
                            <th colspan="3">{{ trans('accounting::accounting.payroll_runs.fields.net_salary') }}</th>
                            <th>{{ number_format((float) $line->net_salary, 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
