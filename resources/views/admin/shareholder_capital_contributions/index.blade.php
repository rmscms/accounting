@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.capital.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.capital.title') }}</h4>
        <a href="{{ route('admin.accounting.shareholder-capital-contributions.create') }}" class="btn btn-primary">{{ trans('accounting::accounting.capital.create') }}</a>
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header">{{ trans('accounting::accounting.capital.summary_title') }}</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.capital.shareholder') }}</th>
                        <th>{{ trans('accounting::accounting.capital.total') }}</th>
                        <th>{{ trans('accounting::accounting.capital.subsidiary') }}</th>
                        <th>{{ trans('accounting::accounting.capital.account_statement') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $sid => $row)
                        @php $sh = $shareholderModels->get($sid); @endphp
                        <tr>
                            <td>{{ $sh?->name ?? ('#'.$sid) }}</td>
                            <td>{{ number_format((float) $row->total_amount, 2) }}</td>
                            <td>
                                @if($sh?->capital_account_id)
                                    <a target="_blank" rel="noopener" href="{{ route('admin.accounting.reports.subsidiary-ledger', ['account_id' => $sh->capital_account_id]) }}">{{ trans('accounting::accounting.capital.subsidiary') }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($sh?->capital_account_id)
                                    <a target="_blank" rel="noopener" href="{{ route('admin.accounting.accounts.statement', $sh->capital_account_id) }}">{{ trans('accounting::accounting.capital.account_statement') }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h5 class="mb-2">{{ trans('accounting::accounting.capital.list') }}</h5>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.common.id') }}</th>
                        <th>{{ trans('accounting::accounting.capital.shareholder') }}</th>
                        <th>{{ trans('accounting::accounting.capital.amount') }}</th>
                        <th>{{ trans('accounting::accounting.capital.journal_date') }}</th>
                        <th>{{ trans('accounting::accounting.capital.source_type') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contributions as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->shareholder?->name }}</td>
                            <td>{{ number_format((float) $row->amount, 2) }} {{ $row->currency_code }}</td>
                            <td>{{ $row->journal_date?->format('Y-m-d') }}</td>
                            <td>{{ $row->source_type === 'bank' ? trans('accounting::accounting.capital.bank') : trans('accounting::accounting.capital.cash_box') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $contributions->links() }}</div>
    </div>
</div>
@endsection
