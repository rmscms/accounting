@if(empty($nodes))
    <div class="text-muted small px-3 py-2 border-top bg-light">زیرحسابی با گردش در این بازه یافت نشد.</div>
@else
    <table class="table table-sm table-hover mb-0">
        <tbody>
            @include('accounting::admin.reports.partials.general-ledger-tree-level', ['nodes' => $nodes])
        </tbody>
    </table>
@endif
