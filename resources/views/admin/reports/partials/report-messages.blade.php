@if(isset($data['error']))
<div class="alert alert-warning">
    <i class="ph-warning me-2"></i>
    {{ $data['error'] }}
</div>
@elseif(isset($data['message']))
<div class="alert alert-info" role="status">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
            <i class="ph-info"></i>
            @if(!empty($data['title']))
            <strong class="mb-0">{{ $data['title'] }}</strong>
            @endif
        </div>
        @if(($data['layout'] ?? '') === 'placeholder')
        <span class="badge bg-secondary">{{ trans('accounting::accounting.reports.placeholder.badge_beta') }}</span>
        @endif
    </div>
    <div class="mb-0">{{ $data['message'] }}</div>
    @if(!empty($data['hint']))
        <div class="mt-2 mb-0 small text-body-secondary">{{ $data['hint'] }}</div>
    @endif
</div>
@endif
