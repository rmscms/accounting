<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\CurrencyRate;
use RMS\Accounting\Models\Currency;

/**
 * نرخ تبدیل ارز تراکنش به ارز پایه دفتر برای ثبت در financial_ledgers.
 */
class LedgerFxResolver
{
    public function __construct(
        protected CurrencyService $currencyService
    ) {
    }

    protected function ledgerBaseCurrency(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
    }

    /**
     * نرخ: هر واحد ارز تراکنش معادل چند واحد ارز پایه.
     */
    public function resolveRateToBase(string $currencyCode, ?string $asOfDate = null): float
    {
        $base = $this->ledgerBaseCurrency();
        $ccy = strtoupper(trim($currencyCode));

        if ($ccy === '' || $ccy === $base) {
            return 1.0;
        }

        if ($this->irrIrtPair($ccy, $base)) {
            return $ccy === 'IRR' && $base === 'IRT' ? 0.1 : ($ccy === 'IRT' && $base === 'IRR' ? 10.0 : 1.0);
        }

        $rateToIrr = $this->rateToIrrForDate($ccy, $asOfDate);
        if ($rateToIrr <= 0) {
            $rateToIrr = $this->currencyService->getCachedRate($ccy);
        }

        if ($base === 'IRT') {
            return $rateToIrr > 0 ? $rateToIrr / 10.0 : 1.0;
        }

        if ($base === 'IRR') {
            return $rateToIrr > 0 ? $rateToIrr : 1.0;
        }

        return $rateToIrr > 0 ? $rateToIrr : 1.0;
    }

    protected function irrIrtPair(string $a, string $b): bool
    {
        $irr = ['IRR', 'IRT'];

        return in_array($a, $irr, true) && in_array($b, $irr, true);
    }

    protected function rateToIrrForDate(string $currencyCode, ?string $asOfDate): float
    {
        $ccy = strtoupper($currencyCode);
        if ($ccy === 'IRR' || $ccy === 'IRT') {
            return $ccy === 'IRR' ? 1.0 : 10.0;
        }

        $date = $asOfDate ? substr($asOfDate, 0, 10) : now()->toDateString();

        $row = CurrencyRate::query()
            ->where('currency_code', $ccy)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();

        return $row ? (float) $row->rate_to_irr : 0.0;
    }
}
