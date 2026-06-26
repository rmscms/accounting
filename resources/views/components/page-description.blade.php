@props([
    'title' => null,
])
<div {{ $attributes->merge(['class' => 'card border-0 bg-light mt-4']) }}>
    <div class="card-body p-4">
        @if($title)
            <h6 class="fw-semibold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="ph-info fs-5"></i>
                <span>{{ $title }}</span>
            </h6>
        @endif
        <div class="text-muted small lh-lg">
            {{ $slot }}
        </div>
    </div>
</div>
