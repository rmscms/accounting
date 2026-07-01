@php
    $name = (string) ($name ?? '');
    $value = trim((string) ($value ?? ''));
    $label = trim((string) ($label ?? ''));
    $placeholder = (string) ($placeholder ?? '—');
    $types = is_array($types ?? null) ? array_values(array_filter($types, static fn ($type) => trim((string) $type) !== '')) : [];
    $valueKey = (string) (($valueKey ?? 'code') === 'id' ? 'id' : 'code');
    $searchUrl = (string) ($searchUrl ?? '');
    $class = trim((string) ($class ?? ''));
    $dataTag = trim((string) ($dataTag ?? ''));
    $text = $label !== '' ? $label : $value;
@endphp

<select class="form-select {{ $class }}"
        name="{{ $name }}"
        data-search-url="{{ $searchUrl }}"
        data-types="{{ implode(',', $types) }}"
        data-value-key="{{ $valueKey }}"
        @if($dataTag !== '') data-account-setting-tag="{{ $dataTag }}" @endif>
    <option value="">{{ $placeholder }}</option>
    @if($value !== '')
        <option value="{{ $value }}" selected>{{ $text }}</option>
    @endif
</select>
