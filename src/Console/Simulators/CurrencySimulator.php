<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * شبیه‌ساز ارزها و نرخ تبدیل
 */
class CurrencySimulator extends BaseSimulator
{
    public function simulate(): void
    {
        $this->info('  🔄 در حال ایجاد ارزها و نرخ تبدیل...');

        // Create currencies
        $this->createCurrencies();

        // Create daily exchange rates for one year
        $this->createExchangeRates();

        $this->success('ارزها: 4 ارز ایجاد شد');
        $this->success('نرخ تبدیل: 1460 نرخ (365 روز × 4 ارز)');
    }

    /**
     * ایجاد ارزها
     */
    protected function createCurrencies(): void
    {
        $currencies = [
            [
                'code' => 'CNY',
                'name' => 'یوان چین',
                'symbol' => '¥',
                'is_base' => true, // ارز پایه
                'active' => true,
            ],
            [
                'code' => 'IRR',
                'name' => 'ریال ایران',
                'symbol' => 'ریال',
                'is_base' => false, // ارز کارکرد
                'active' => true,
            ],
            [
                'code' => 'USD',
                'name' => 'دلار آمریکا',
                'symbol' => '$',
                'is_base' => false,
                'active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'یورو',
                'symbol' => '€',
                'is_base' => false,
                'active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->insert(array_merge($currency, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * ایجاد نرخ تبدیل روزانه برای یک سال
     */
    protected function createExchangeRates(): void
    {
        $rates = [];
        $startDate = Carbon::create($this->year, 1, 1); // فروردین 1
        $endDate = $startDate->copy()->addYear(); // تا پایان سال

        // نرخ‌های پایه (میانگین)
        $baseRates = [
            'CNY' => 7250,   // یوان به ریال: 7000-7500
            'USD' => 55000,  // دلار به ریال: 50000-60000
            'EUR' => 60000,  // یورو به ریال: 55000-65000
        ];

        // Volatility (نوسان روزانه)
        $volatility = [
            'CNY' => 0.015,  // 1.5% نوسان
            'USD' => 0.020,  // 2% نوسان
            'EUR' => 0.020,  // 2% نوسان
        ];

        $currentDate = $startDate->copy();
        $previousRates = $baseRates;

        while ($currentDate->lte($endDate)) {
            foreach (['CNY', 'USD', 'EUR'] as $currency) {
                // محاسبه نرخ با نوسان واقعی
                $change = ($this->randomNormal(0, 1) * $volatility[$currency]);
                $newRate = $previousRates[$currency] * (1 + $change);
                
                // محدود کردن نوسانات
                $minRate = $baseRates[$currency] * 0.90;
                $maxRate = $baseRates[$currency] * 1.10;
                $newRate = max($minRate, min($maxRate, $newRate));
                
                $previousRates[$currency] = $newRate;

                $rates[] = [
                    'currency_code' => $currency,
                    'rate_to_irr' => round($newRate, 2),
                    'rate_date' => $currentDate->format('Y-m-d'),
                    'source' => 'simulation',
                    'created_at' => now(),
                ];
            }

            $currentDate->addDay();
        }

        // Bulk insert
        $this->bulkInsert('currency_rates', $rates, 1000);
    }
}
