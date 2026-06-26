@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? trans('accounting::accounting.currency.title'))

@section('content')
<div class="container-fluid">
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-0">{{ trans('accounting::accounting.menu.currencies') }}</h5>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.accounting.currencies.reference-rates') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ph-arrows-clockwise me-1"></i>{{ trans('accounting::accounting.currency.reference_rates') }}
                </a>
                <a href="{{ route('admin.accounting.currencies.create') }}" class="btn btn-primary btn-sm">
                    <i class="ph-plus me-1"></i>{{ trans('accounting::accounting.currency.create') }}
                </a>
            </div>
        </div>
        <div class="card-body border-bottom bg-light-subtle">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <span class="text-muted small d-block">{{ trans('accounting::accounting.currency.reference_currency') }}</span>
                    <strong>{{ $referenceCurrencyCode ?: '—' }}</strong>
                </div>
                <div>
                    <span class="text-muted small d-block">{{ trans('accounting::accounting.currency.base_currency') }}</span>
                    <strong>{{ $baseCurrencyCode ?: '—' }}</strong>
                </div>
                <div>
                    <span class="text-muted small d-block">{{ trans('accounting::accounting.currency.latest_reference_rate') }}</span>
                    @if($latestReferenceRate)
                        <strong>{{ number_format((float) $latestReferenceRate->rate_to_base, 6) }}</strong>
                        <span class="text-muted small">({{ (string) $latestReferenceRate->rate_date }})</span>
                    @else
                        <strong>—</strong>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.currency.code') }}</th>
                        <th>{{ trans('accounting::accounting.currency.name') }}</th>
                        <th>{{ trans('accounting::accounting.currency.symbol') }}</th>
                        <th>{{ trans('accounting::accounting.currency.decimal_places') }}</th>
                        <th>{{ trans('accounting::accounting.currency.is_base') }}</th>
                        <th>{{ trans('accounting::accounting.currency.is_reference') }}</th>
                        <th>{{ trans('accounting::accounting.common.status') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.currency.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($currencies as $row)
                        <tr>
                            <td class="fw-semibold">{{ strtoupper((string) $row->code) }}</td>
                            <td>{{ (string) $row->name }}</td>
                            <td>{{ (string) ($row->symbol ?? '—') }}</td>
                            <td>{{ (int) ($row->decimals ?? 0) }}</td>
                            <td>
                                @if((bool) $row->is_base)
                                    <span class="badge bg-info">{{ trans('accounting::accounting.common.yes') }}</span>
                                @else
                                    <span class="text-muted">{{ trans('accounting::accounting.common.no') }}</span>
                                @endif
                            </td>
                            <td>
                                @if((bool) ($row->is_reference ?? false))
                                    <span class="badge bg-primary">{{ trans('accounting::accounting.common.yes') }}</span>
                                @else
                                    <span class="text-muted">{{ trans('accounting::accounting.common.no') }}</span>
                                @endif
                            </td>
                            <td>
                                @if((bool) $row->active)
                                    <span class="badge bg-success">{{ trans('accounting::accounting.common.active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ trans('accounting::accounting.common.inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.accounting.currencies.edit', ['currency' => $row->code]) }}" class="btn btn-light btn-sm border">
                                    <i class="ph-pencil me-1"></i>{{ trans('accounting::accounting.currency.edit') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ trans('accounting::accounting.common.none') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

