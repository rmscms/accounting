@php
    $routeName = $routeName ?? 'admin.accounting.settings.index';
    $tag = isset($tag) ? trim((string) $tag) : '';
    $label = isset($label) && trim((string) $label) !== ''
        ? (string) $label
        : (string) trans('accounting::accounting.payroll_insurance.go_to_settings');
    $class = isset($class) && trim((string) $class) !== '' ? (string) $class : 'btn btn-sm btn-outline-secondary';
    $url = route($routeName, $tag !== '' ? ['account_setting_tag' => $tag] : []);
@endphp
<a href="{{ $url }}" class="{{ $class }}">
    {{ $label }}
</a>
