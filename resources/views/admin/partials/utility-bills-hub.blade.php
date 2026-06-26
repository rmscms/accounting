@if(Route::has('admin.accounting.utility-bills.index'))
    <div class="col-12">
        <div class="card border-start border-secondary border-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">
                            <i class="ph-receipt me-1 text-secondary"></i>
                            {{ trans('accounting::accounting.utility_bills.hub.title') }}
                        </h5>
                        <p class="text-muted mb-0 small">{{ trans('accounting::accounting.utility_bills.hub.lead') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if(Route::has('admin.accounting.utility-bills.create'))
                            <a href="{{ route('admin.accounting.utility-bills.create') }}" class="btn btn-sm btn-primary">
                                <i class="ph-plus me-1"></i>
                                {{ trans('accounting::accounting.utility_bills.hub.open_create') }}
                            </a>
                        @endif
                        <a href="{{ route('admin.accounting.utility-bills.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ph-list me-1"></i>
                            {{ trans('accounting::accounting.utility_bills.hub.open_list') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
