<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;

/**
 * سرویس مدیریت ارزها و نرخ ارز
 */
class CurrencyService
{
    /**
     * دریافت نرخ ارز فعلی
     */
    public function getCurrentRate(string $currencyCode): ?CurrencyRate
    {
        return CurrencyRate::where('currency_code', $currencyCode)
            ->whereDate('rate_date', '<=', now())
            ->orderBy('rate_date', 'desc')
            ->first();
    }

    /**
     * دریافت نرخ ارز با cache
     */
    public function getCachedRate(string $currencyCode): float
    {
        $cacheKey = "currency_rate_{$currencyCode}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($currencyCode) {
            $rate = $this->getCurrentRate($currencyCode);
            return $rate ? $rate->rate_to_irr : 1;
        });
    }

    /**
     * تبدیل مبلغ به ریال
     */
    public function convertToIRR(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'IRR') {
            return $amount;
        }

        $rate = $this->getCachedRate($fromCurrency);
        return $amount * $rate;
    }

    /**
     * تبدیل از ریال به ارز دیگر
     */
    public function convertFromIRR(float $amountIRR, string $toCurrency): float
    {
        if ($toCurrency === 'IRR') {
            return $amountIRR;
        }

        $rate = $this->getCachedRate($toCurrency);
        return $rate > 0 ? $amountIRR / $rate : 0;
    }

    /**
     * ثبت نرخ ارز جدید
     */
    public function recordRate(string $currencyCode, float $rate, ?string $date = null, string $source = 'manual'): CurrencyRate
    {
        $rateDate = $date ?? now()->toDateString();

        // Clear cache
        Cache::forget("currency_rate_{$currencyCode}");

        return CurrencyRate::create([
            'currency_code' => $currencyCode,
            'rate_to_irr' => $rate,
            'rate_date' => $rateDate,
            'source' => $source,
        ]);
    }

    /**
     * دریافت تاریخچه نرخ ارز
     */
    public function getRateHistory(string $currencyCode, int $days = 30)
    {
        return CurrencyRate::where('currency_code', $currencyCode)
            ->whereDate('rate_date', '>=', now()->subDays($days))
            ->orderBy('rate_date', 'desc')
            ->get();
    }

    /**
     * دریافت لیست ارزهای فعال
     */
    public function getActiveCurrencies()
    {
        return Currency::where('active', true)
            ->orderBy('code')
            ->get();
    }
}
