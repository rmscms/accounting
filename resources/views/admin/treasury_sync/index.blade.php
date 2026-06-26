@extends('cms::admin.layout.index')

@section('title', trans('accounting::accounting.treasury_sync.page_title'))

@section('content')
<div class="container-fluid">
    <div class="card border-success border-opacity-25 shadow-sm mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-1">
                    <i class="ph-arrows-clockwise me-1 text-success"></i>{{ trans('accounting::accounting.treasury_sync.page_title') }}
                </h5>
                <small class="text-muted">{{ trans('accounting::accounting.treasury_sync.page_subtitle') }}</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-light btn-sm">
                    <i class="ph-house me-1"></i>{{ trans('accounting::accounting.treasury_sync.back_dashboard') }}
                </a>
                <a href="{{ route('admin.accounting.bank-transfers.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ph-arrows-left-right me-1"></i>{{ trans('accounting::accounting.treasury_sync.go_transfers') }}
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light bg-opacity-50">
                        <div class="small text-muted">{{ trans('accounting::accounting.treasury_sync.summary.total') }}</div>
                        <div class="h4 mb-0">{{ number_format((int) ($summary['total'] ?? 0)) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-success bg-opacity-10">
                        <div class="small text-muted">{{ trans('accounting::accounting.treasury_sync.summary.synced') }}</div>
                        <div class="h4 mb-0 text-success">{{ number_format((int) ($summary['synced'] ?? 0)) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-warning bg-opacity-10">
                        <div class="small text-muted">{{ trans('accounting::accounting.treasury_sync.summary.out_of_sync') }}</div>
                        <div class="h4 mb-0 text-warning">{{ number_format((int) ($summary['out_of_sync'] ?? 0)) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-secondary bg-opacity-10">
                        <div class="small text-muted">{{ trans('accounting::accounting.treasury_sync.summary.missing_account') }}</div>
                        <div class="h4 mb-0 text-secondary">{{ number_format((int) ($summary['missing_account'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-secondary border-opacity-25 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>{{ trans('accounting::accounting.treasury_sync.columns.endpoint') }}</th>
                        <th>{{ trans('accounting::accounting.treasury_sync.columns.account') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.treasury_sync.columns.recorded_balance') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.treasury_sync.columns.ledger_balance') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.treasury_sync.columns.difference') }}</th>
                        <th>{{ trans('accounting::accounting.treasury_sync.columns.status') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.treasury_sync.columns.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $statusBadge = match($row['status']) {
                                'synced' => '<span class="badge bg-success">' . e(trans('accounting::accounting.treasury_sync.statuses.synced')) . '</span>',
                                'out_of_sync' => '<span class="badge bg-warning text-dark">' . e(trans('accounting::accounting.treasury_sync.statuses.out_of_sync')) . '</span>',
                                default => '<span class="badge bg-secondary">' . e(trans('accounting::accounting.treasury_sync.statuses.missing_account')) . '</span>',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row['label'] }}</div>
                                <div class="small text-muted">{{ $row['type_label'] }} #{{ $row['id'] }}</div>
                            </td>
                            <td>
                                @if($row['account_id'])
                                    <div class="font-monospace">{{ $row['account_code'] }}</div>
                                    <div class="small text-muted">{{ $row['account_name'] }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $row['recorded_balance'], 2, '.', ',') }}</td>
                            <td class="text-end">
                                @if($row['ledger_balance'] !== null)
                                    {{ number_format((float) $row['ledger_balance'], 2, '.', ',') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                @if($row['difference'] !== null)
                                    <span class="{{ ((float) $row['difference']) === 0.0 ? 'text-success' : 'text-warning' }}">
                                        {{ number_format((float) $row['difference'], 2, '.', ',') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{!! $statusBadge !!}</td>
                            <td class="text-end">
                                @if($row['can_sync'])
                                    <form method="post" action="{{ route('admin.accounting.treasury-sync.sync-one', ['type' => $row['type'], 'id' => $row['id']]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="ph-arrows-clockwise me-1"></i>{{ trans('accounting::accounting.treasury_sync.sync_one_btn') }}
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                        {{ trans('accounting::accounting.treasury_sync.sync_disabled_btn') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ trans('accounting::accounting.treasury_sync.empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

