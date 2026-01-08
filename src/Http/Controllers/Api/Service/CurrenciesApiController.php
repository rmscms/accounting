<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\ExchangeRate;
use RMS\Accounting\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Service API Controller for Currencies
 * برای دریافت نرخ ارز توسط پکیج‌های دیگر
 * 
 * @group Service API - Currencies
 */
class CurrenciesApiController
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get current exchange rate for a currency
     * 
     * @urlParam code string required کد ارز (مثل USD, EUR, CNY)
     * @queryParam to_currency string کد ارز مقصد (پیش‌فرض: IRR)
     * @queryParam date date تاریخ (پیش‌فرض: امروز)
     */
    public function getCurrentRate(string $code, Request $request): JsonResponse
    {
        $toCurrency = $request->get('to_currency', config('accounting.default_currency', 'IRR'));
        $date = $request->get('date', Carbon::now());

        try {
            // Get currency
            $currency = Currency::where('code', strtoupper($code))
                ->where('active', true)
                ->first();

            if (!$currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not found or inactive',
                ], 404);
            }

            // Get exchange rate
            $rate = $this->currencyService->getExchangeRate(
                strtoupper($code),
                $toCurrency,
                Carbon::parse($date)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'from_currency' => strtoupper($code),
                    'to_currency' => $toCurrency,
                    'rate' => $rate,
                    'date' => Carbon::parse($date)->format('Y-m-d'),
                    'currency_name' => $currency->name,
                    'currency_symbol' => $currency->symbol,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get exchange rate: ' . $e->getMessage(),
            ], 500);
        }
    }
}
