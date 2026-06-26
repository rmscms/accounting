@extends('cms::admin.layout.index')

@section('title', trans('accounting::accounting.purchases_dashboard.page_title'))

@section('content')
<div class="container-fluid">
    <div class="card border-start border-4 border-primary border-opacity-50 mb-3">
        <div class="card-body">
            <h5 class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.intro_title') }}</h5>
            <p class="text-muted mb-0">{{ trans('accounting::accounting.purchases_dashboard.intro_p1') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100 border border-secondary border-opacity-25">
                <div class="card-header fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_title') }}</div>
                <div class="card-body small">
                    <p class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_use') }}</p>
                    <p class="mb-2 text-danger-emphasis">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_avoid') }}</p>
                    <p class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_mistake') }}</p>
                    <p class="mb-0 text-muted">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_note') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 border border-secondary border-opacity-25">
                <div class="card-header fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.path_po_title') }}</div>
                <div class="card-body small">
                    <p class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.path_po_use') }}</p>
                    <p class="mb-2 text-danger-emphasis">{{ trans('accounting::accounting.purchases_dashboard.path_po_avoid') }}</p>
                    <p class="mb-0">{{ trans('accounting::accounting.purchases_dashboard.path_po_mistake') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.decision_title') }}</div>
        <div class="card-body small">
            <ul class="mb-0">
                <li class="mb-1"><strong>{{ trans('accounting::accounting.purchases_dashboard.decision_q1') }}</strong> {{ trans('accounting::accounting.purchases_dashboard.decision_a1') }}</li>
                <li><strong>{{ trans('accounting::accounting.purchases_dashboard.decision_q2') }}</strong> {{ trans('accounting::accounting.purchases_dashboard.decision_a2') }}</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.standards_title') }}</div>
        <div class="card-body small">
            <p class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.standards_p1') }}</p>
            <p class="mb-2">{{ trans('accounting::accounting.purchases_dashboard.standards_p2') }}</p>
            <p class="mb-0 text-muted">{{ trans('accounting::accounting.purchases_dashboard.standards_p3') }}</p>
        </div>
    </div>

    <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-secondary">
                <tr>
                    <th></th>
                    <th>{{ trans('accounting::accounting.purchases_dashboard.table_goal') }}</th>
                    <th>{{ trans('accounting::accounting.purchases_dashboard.table_warehouse') }}</th>
                    <th>{{ trans('accounting::accounting.purchases_dashboard.table_accounts') }}</th>
                    <th>{{ trans('accounting::accounting.purchases_dashboard.table_example') }}</th>
                    <th>{{ trans('accounting::accounting.purchases_dashboard.table_doc') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="text-nowrap">{{ trans('accounting::accounting.purchases_dashboard.path_invoice_title') }}</th>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_invoice_goal') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_invoice_wh') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_invoice_acc') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_invoice_ex') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_invoice_doc') }}</td>
                </tr>
                <tr>
                    <th class="text-nowrap">{{ trans('accounting::accounting.purchases_dashboard.path_po_title') }}</th>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_po_goal') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_po_wh') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_po_acc') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_po_ex') }}</td>
                    <td>{{ trans('accounting::accounting.purchases_dashboard.row_po_doc') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-success bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                <i class="ph-hand-coins text-success"></i>
                {{ trans('accounting::accounting.purchases_dashboard.workflow_payment_title') }}
            </h6>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">{{ trans('accounting::accounting.purchases_dashboard.workflow_payment_p1') }}</p>
            <a href="{{ route('admin.accounting.supplier-payments.create') }}" class="btn btn-success btn-sm me-1">
                <i class="ph-bank me-1"></i>{{ trans('accounting::accounting.purchases_dashboard.cta_payment') }}
            </a>
            <a href="{{ route('admin.accounting.supplier-payments.index') }}" class="btn btn-light btn-sm border">{{ trans('accounting::accounting.purchases_dashboard.link_payment_list') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.cta_invoice') }}</h6>
                    <p class="card-text small text-muted">{{ trans('accounting::accounting.purchases_dashboard.cta_invoice_sub') }}</p>
                    <a href="{{ route('admin.accounting.supplier-invoices.create') }}" class="btn btn-primary btn-sm me-1">{{ trans('accounting::accounting.purchases_dashboard.cta_invoice') }}</a>
                    <a href="{{ route('admin.accounting.supplier-invoices.index') }}" class="btn btn-light btn-sm border">{{ trans('accounting::accounting.purchases_dashboard.link_invoice_list') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-semibold">{{ trans('accounting::accounting.purchases_dashboard.cta_po') }}</h6>
                    <p class="card-text small text-muted">{{ trans('accounting::accounting.purchases_dashboard.cta_po_sub') }}</p>
                    <a href="{{ route('admin.accounting.purchase-orders.create') }}" class="btn btn-primary btn-sm me-1">{{ trans('accounting::accounting.purchases_dashboard.cta_po') }}</a>
                    <a href="{{ route('admin.accounting.purchase-orders.index') }}" class="btn btn-light btn-sm border">{{ trans('accounting::accounting.purchases_dashboard.link_po_list') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
