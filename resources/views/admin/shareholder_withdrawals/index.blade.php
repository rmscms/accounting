@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.withdrawals.list'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.withdrawals.list') }}</h4>
        <a href="{{ route('admin.accounting.shareholder-withdrawals.create') }}" class="btn btn-primary">{{ trans('accounting::accounting.withdrawals.create') }}</a>
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.common.id') }}</th>
                        <th>{{ trans('accounting::accounting.withdrawals.shareholder') }}</th>
                        <th>{{ trans('accounting::accounting.withdrawals.amount') }}</th>
                        <th>{{ trans('accounting::accounting.withdrawals.journal_date') }}</th>
                        <th>{{ trans('accounting::accounting.withdrawals.source_type') }}</th>
                        <th>{{ trans('accounting::accounting.common.status') }}</th>
                        <th>{{ trans('accounting::accounting.shareholders.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($withdrawals as $row)
                        @php
                            $journalStatus = (string) ($row->manualJournal->status ?? '');
                            $isDraft = $journalStatus === 'draft';
                        @endphp
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->shareholder?->name }}</td>
                            <td>{{ number_format((float) $row->amount, 2) }} {{ $row->currency_code }}</td>
                            <td>{{ $row->journal_date?->format('Y-m-d') }}</td>
                            <td>
                                @if($row->source_type === 'bank')
                                    {{ trans('accounting::accounting.withdrawals.source_bank') }}
                                @elseif($row->source_type === 'cash')
                                    {{ trans('accounting::accounting.withdrawals.source_cash') }}
                                @else
                                    {{ $row->source_type }}
                                @endif
                            </td>
                            <td>
                                @if($isDraft)
                                    <span class="badge bg-warning text-dark">{{ trans('accounting::accounting.withdrawals.status_draft') }}</span>
                                @else
                                    <span class="badge bg-success">{{ trans('accounting::accounting.withdrawals.status_posted') }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($isDraft)
                                    <form method="post" action="{{ route('admin.accounting.shareholder-withdrawals.post', $row->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ trans('accounting::accounting.withdrawals.post_document') }}</button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ trans('accounting::accounting.withdrawals.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $withdrawals->links() }}</div>
    </div>
</div>
@endsection
