@if(!empty($data['treasury_negative_balances']))
    @php
        $asOf = isset($data['period']['end']) ? \Carbon\Carbon::parse($data['period']['end']) : null;
        try {
            $asOfLabel = $asOf ? \RMS\Helper\persian_date($asOf, 'Y/m/d') : '';
        } catch (\Throwable) {
            $asOfLabel = $asOf ? $asOf->format('Y-m-d') : '';
        }
    @endphp
    <div class="alert alert-danger border-0 shadow-sm mb-3 acct-gl-treasury-negative-alert" role="alert">
        <div class="d-flex gap-2 align-items-start">
            <i class="ph-warning-octagon fs-4 flex-shrink-0 mt-1" aria-hidden="true"></i>
            <div class="flex-grow-1">
                <h2 class="alert-heading h6 mb-2">{{ trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.title') }}</h2>
                <p class="small mb-2 mb-md-3 text-body-secondary">
                    {{ trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.lead', ['date' => $asOfLabel]) }}
                </p>
                <ul class="small mb-2 mb-md-3 ps-3">
                    @foreach($data['treasury_negative_balances'] as $row)
                        <li class="mb-1">
                            {{ trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.line', [
                                'sources' => $row['source_labels'] ?? '',
                                'code' => $row['account_code'] ?? '',
                                'name' => $row['account_name'] ?? '',
                                'balance' => $row['balance_formatted'] ?? '',
                            ]) }}
                        </li>
                    @endforeach
                </ul>
                <p class="mb-0 small fw-semibold">{{ trans('accounting::accounting.reports.general_ledger.treasury_negative_alert.footer_action') }}</p>
            </div>
        </div>
    </div>
@endif
