<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\ExpenseCategory;

class ExpenseCategoryCodeService
{
    public function config(): array
    {
        return config('accounting.expense_categories', []);
    }

    public function normalize(string $raw): string
    {
        $c = $this->config();
        $prefix = (string) ($c['code_prefix'] ?? '');
        $sep = (string) ($c['code_separator'] ?? '-');
        $enforceUpper = (bool) ($c['enforce_uppercase'] ?? true);

        $s = preg_replace('/\s+/u', '', trim($raw)) ?? '';
        if ($enforceUpper) {
            $s = mb_strtoupper($s, 'UTF-8');
        }

        if ($prefix !== '') {
            $expected = $prefix . $sep;
            if (! str_starts_with($s, $expected)) {
                if (str_starts_with($s, $prefix)) {
                    $rest = substr($s, strlen($prefix));
                    $rest = ltrim($rest, $sep);
                    $s = $expected . $rest;
                } else {
                    $s = $expected . ltrim($s, $sep);
                }
            }
        }

        return $s;
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function validateFormat(string $normalized): array
    {
        $c = $this->config();
        $min = max(1, (int) ($c['code_min_length'] ?? 2));
        $max = min(50, max($min, (int) ($c['code_max_length'] ?? 50)));
        $pattern = (string) ($c['code_pattern'] ?? '/^[A-Z0-9_-]+$/');

        $len = mb_strlen($normalized, 'UTF-8');
        if ($len < $min || $len > $max) {
            return [
                'ok' => false,
                'message' => trans('accounting::accounting.expense_category_form.validation_code_length', ['min' => $min, 'max' => $max]),
            ];
        }

        if ($pattern !== '') {
            $ok = @preg_match($pattern, $normalized);
            if ($ok === false) {
                // الگوی نامعتبر در config — از اعمال regex صرف‌نظر می‌شود
            } elseif ($ok !== 1) {
                return [
                    'ok' => false,
                    'message' => trans('accounting::accounting.expense_category_form.validation_code_pattern'),
                ];
            }
        }

        return ['ok' => true, 'message' => null];
    }

    public function isAvailable(string $normalized, ?int $exceptId = null): bool
    {
        $q = ExpenseCategory::query()->where('code', $normalized);
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        return ! $q->exists();
    }

    /**
     * پیشنهاد کد بعدی برای فرم ایجاد (همیشه مقدار می‌دهد؛ مستقل از auto_suggest_next).
     * با پیشوند تنظیمات: آخرین پسوند عددی همان خانواده را یکی زیاد می‌کند؛ بدون پیشوند: CAT-0001 به‌صورت متوالی.
     */
    public function suggestNextCode(): string
    {
        $c = $this->config();
        $prefix = (string) ($c['code_prefix'] ?? '');
        $sep = (string) ($c['code_separator'] ?? '-');

        if ($prefix !== '') {
            $p = $prefix . $sep;
            $last = ExpenseCategory::query()
                ->where('code', 'like', $p . '%')
                ->orderByDesc('code')
                ->value('code');
            if (! $last) {
                return $p . '001';
            }
            $suffix = substr($last, strlen($p));
            if (preg_match('/^(\d+)$/', $suffix, $m)) {
                return $p . str_pad((string) ((int) $m[1] + 1), max(3, strlen($m[1])), '0', STR_PAD_LEFT);
            }

            return $p . '001';
        }

        $next = (int) ExpenseCategory::query()->max('id') + 1;
        if ($next < 1) {
            $next = 1;
        }

        return 'CAT-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
