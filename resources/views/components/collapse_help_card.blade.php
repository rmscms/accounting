{{--
    کارت راهنمای تاشو (پیش‌فرض بسته) — استفاده مجدد در فرم‌های پکیج accounting

    پارامترهای پیش‌بینی‌شده هنگام @include:
    - collapseId (string, اختیاری): id یکتا برای collapse
    - toggleLabel (string): متن دکمه هدر
    - title (string|null): عنوان داخل بدنه
    - paragraphs (array): آرایه رشته‌ها
    - body_html (string|null): اختیاری — HTML ایمن از ترجمه با {!! !!}
    - append_html (string|null): اختیاری — بعد از پاراگراف‌ها/بدنه (مثلاً بلوک رنگی وضعیت‌ها)
    - cardClass (string): کلاس اضافه برای کارت (پیش‌فرض mt-3)
--}}
@php
    $collapseId = $collapseId ?? ('accounting-collapse-help-' . uniqid());
    $cardClass = $cardClass ?? 'mt-3';
    $paragraphsRaw = $paragraphs ?? [];
    if (is_array($paragraphsRaw)) {
        $paragraphs = $paragraphsRaw;
    } elseif ($paragraphsRaw instanceof \Traversable) {
        $paragraphs = iterator_to_array($paragraphsRaw, false);
    } elseif (is_string($paragraphsRaw) && trim($paragraphsRaw) !== '') {
        $paragraphs = [$paragraphsRaw];
    } else {
        $paragraphs = [];
    }
@endphp
<div class="card accounting-collapse-help {{ $cardClass }}">
    <div class="card-header py-2">
        <button class="btn btn-link text-decoration-none p-0 collapsed d-flex align-items-center w-100 text-start"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="false"
                aria-controls="{{ $collapseId }}">
            <i class="ph-caret-down me-2 accounting-collapse-help__caret"></i>
            <span>{{ $toggleLabel ?? '' }}</span>
        </button>
    </div>
    <div id="{{ $collapseId }}" class="collapse">
        <div class="card-body">
            @if(!empty($title))
                <h6 class="text-primary">{{ $title }}</h6>
            @endif
            @foreach($paragraphs as $paragraph)
                <p class="mb-3 text-muted">{{ $paragraph }}</p>
            @endforeach
            @if(!empty($body_html))
                <div class="accounting-collapse-help__body text-muted mb-0">{!! $body_html !!}</div>
            @endif
            @if(!empty($append_html))
                <div class="accounting-collapse-help__append border-top pt-3 mt-3">{!! $append_html !!}</div>
            @endif
        </div>
    </div>
</div>
<style>
    .accounting-collapse-help .accounting-collapse-help__caret {
        transition: transform 0.2s ease;
    }
    .accounting-collapse-help button[aria-expanded="true"] .accounting-collapse-help__caret {
        transform: rotate(180deg);
    }
</style>
