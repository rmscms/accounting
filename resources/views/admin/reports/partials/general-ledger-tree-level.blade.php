@foreach($nodes as $node)
    @php
        $baseCurrency = strtoupper((string) config('accounting.default_currency', 'IRR'));
        $nodeCurrency = strtoupper((string) ($node['currency_code'] ?? ''));
        $showNodeCurrency = $nodeCurrency !== '' && $nodeCurrency !== $baseCurrency;
    @endphp
    <tr class="align-middle">
        <td class="text-nowrap">{{ $node['code'] }}</td>
        <td>
            @if(!empty($node['has_children']))
                <button type="button"
                        class="btn btn-link btn-sm p-0 align-baseline text-body gl-tree-toggle"
                        data-bs-toggle="collapse"
                        data-bs-target="#gl-node-{{ $node['id'] }}"
                        aria-expanded="false"
                        aria-controls="gl-node-{{ $node['id'] }}"
                        title="نمایش / مخفی کردن زیرحساب‌ها">
                    <i class="ph-caret-left gl-tree-chevron"></i>
                </button>
            @else
                <span class="d-inline-block" style="width: 1.25rem" aria-hidden="true"></span>
            @endif
            <span class="ms-1">{{ $node['name'] }}</span>
            @if($showNodeCurrency)
                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle ms-1">{{ $nodeCurrency }}</span>
            @endif
        </td>
        <td class="text-end">{{ number_format($node['subtree_debit']) }}</td>
        <td class="text-end">{{ number_format($node['subtree_credit']) }}</td>
        <td class="text-end">{{ number_format($node['balance']) }}</td>
    </tr>
    @if(!empty($node['has_children']))
        <tr>
            <td colspan="5" class="p-0 border-0">
                <div class="collapse gl-branch-load border-top"
                     id="gl-node-{{ $node['id'] }}"
                     data-parent-id="{{ $node['id'] }}"
                     data-loaded="0">
                    <div class="gl-ajax-placeholder text-muted small px-3 py-2 bg-light">
                        با باز کردن، زیرحساب‌ها از سرور بارگذاری می‌شوند.
                    </div>
                </div>
            </td>
        </tr>
    @endif
@endforeach
