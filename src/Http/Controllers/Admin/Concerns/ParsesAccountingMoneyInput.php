<?php

namespace RMS\Accounting\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use RMS\Accounting\Models\Currency;
use RMS\Core\Models\Setting;

/**
 * نرمال‌سازی ورودی مبلغ و حل ارز/اعشار پیش‌فرض برای فرم‌های ادمین accounting (بدون وابستگی به App).
 */
trait ParsesAccountingMoneyInput
{
    /**
     * ارز پیش‌فرض از جدول currencies (is_base)، سپس اولین ارز فعال.
     */
    protected function resolveDefaultCurrencyCode(): string
    {
        return Currency::resolveBaseCurrencyCode(
            strtoupper(trim((string) config('accounting.base_currency', config('accounting.default_currency', 'IRR'))))
        );
    }

    /**
     * تعداد ارقام اعشار مبالغ از تنظیمات accounting.decimal_places.
     */
    protected function resolveAccountingAmountDecimalPlaces(): int
    {
        $n = (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0));

        return min(4, max(0, $n));
    }

    protected function parseDecimalAmount(string $raw): ?float
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $s = str_replace($persianDigits, $latin, trim($raw));
        $s = str_replace([',', '،', ' '], '', $s);
        if ($s === '') {
            return null;
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * رشتهٔ نرمال‌شده برای merge در request (بدون گرد کردن؛ validation بعدی min/max را اعمال می‌کند).
     */
    protected function normalizeDecimalRequestValue(string $raw): ?string
    {
        $n = $this->parseDecimalAmount($raw);
        if ($n === null) {
            return null;
        }

        return (string) $n;
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function mergeParsedDecimalFields(Request $request, array $keys, ?string $zeroKeyForEmpty = null): void
    {
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $raw = (string) $request->input($key);
            $normalized = $this->normalizeDecimalRequestValue($raw);
            if ($normalized !== null) {
                $request->merge([$key => $normalized]);
            } elseif ($zeroKeyForEmpty !== null && $key === $zeroKeyForEmpty) {
                $request->merge([$key => '0']);
            }
        }
    }
}
