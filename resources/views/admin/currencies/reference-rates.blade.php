@extends('cms::admin.layout.index')

@section('title', $htmlPageTitle ?? trans('accounting::accounting.currency.title'))

@section('content')
<div class="container-fluid">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-0">{{ trans('accounting::accounting.currency.reference_rates') }}</h5>
            </div>
            <a href="{{ route('admin.accounting.currencies.index') }}" class="btn btn-light btn-sm">
                <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_resource_forms.back_to_list') }}
            </a>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success py-2">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger py-2">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-info py-2">
                <div><strong>{{ trans('accounting::accounting.currency.base_currency') }}:</strong> {{ $baseCurrencyCode ?: '—' }}</div>
                <div><strong>{{ trans('accounting::accounting.currency.reference_currency') }}:</strong> {{ $referenceCurrencyCode ?: '—' }}</div>
            </div>
            @php
                $referenceRatesGuideParagraphs = [
                    trans('accounting::accounting.currency.reference_rates_help.p1'),
                    trans('accounting::accounting.currency.reference_rates_help.p2'),
                    trans('accounting::accounting.currency.reference_rates_help.p3'),
                    trans('accounting::accounting.currency.reference_rates_help.p4'),
                ];
            @endphp
            @include('accounting::components.collapse_help_card', [
                'collapseId' => 'accounting-reference-rates-help',
                'cardClass' => 'mb-3',
                'toggleLabel' => trans('accounting::accounting.currency.reference_rates_help.toggle'),
                'title' => trans('accounting::accounting.currency.reference_rates_help.title'),
                'paragraphs' => $referenceRatesGuideParagraphs,
            ])

            <form method="post" action="{{ route('admin.accounting.currencies.reference-rates.store') }}" class="row g-3 mb-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.currency.rate_date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="rate_date" class="form-control @error('rate_date') is-invalid @enderror"
                           value="{{ old('rate_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.currency.latest_reference_rate') }} <span class="text-danger">*</span></label>
                    <input type="number" step="0.000001" min="0.000001" name="rate_to_base"
                           class="form-control @error('rate_to_base') is-invalid @enderror"
                           value="{{ old('rate_to_base', ($referenceCurrencyCode === $baseCurrencyCode && $referenceCurrencyCode !== null) ? '1' : '') }}"
                           @if($referenceCurrencyCode === $baseCurrencyCode && $referenceCurrencyCode !== null) readonly @endif
                           required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.common.notes') }}</label>
                    <input type="text" name="notes" maxlength="1000"
                           class="form-control @error('notes') is-invalid @enderror"
                           value="{{ old('notes') }}">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-floppy-disk me-1"></i>{{ trans('accounting::accounting.common.save') }}
                    </button>
                </div>
            </form>

            <form method="post" action="{{ route('admin.accounting.currencies.recalculate-reference') }}" class="border rounded p-3 mb-3">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('accounting::accounting.currency.from_date') }}</label>
                        <input type="date" name="from_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('accounting::accounting.currency.to_date') }}</label>
                        <input type="date" name="to_date" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="hidden" name="force" value="0">
                            <input class="form-check-input" type="checkbox" id="force_recalc" name="force" value="1">
                            <label class="form-check-label" for="force_recalc">{{ trans('accounting::accounting.currency.force_recalculate') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-md-end">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="ph-arrow-clockwise me-1"></i>{{ trans('accounting::accounting.currency.recalculate_action') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h6 class="mb-0">{{ trans('accounting::accounting.currency.rate_history') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.currency.base_currency') }}</th>
                        <th>{{ trans('accounting::accounting.currency.reference_currency') }}</th>
                        <th>{{ trans('accounting::accounting.currency.latest_reference_rate') }}</th>
                        <th>{{ trans('accounting::accounting.currency.rate_date') }}</th>
                        <th>{{ trans('accounting::accounting.common.created_at') }}</th>
                        <th>{{ trans('accounting::accounting.common.notes') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rates as $row)
                        <tr>
                            <td>{{ (int) $row->id }}</td>
                            <td>{{ (string) $row->base_currency_code }}</td>
                            <td>{{ (string) $row->reference_currency_code }}</td>
                            <td>{{ number_format((float) $row->rate_to_base, 6) }}</td>
                            <td>{{ (string) $row->rate_date }}</td>
                            <td>{{ (string) $row->created_at }}</td>
                            <td>{{ (string) ($row->notes ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ trans('accounting::accounting.common.none') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
