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
                'decimals' => 2,
                'is_base' => true, // ارز پایه
            ],
            [
                'code' => 'IRT',
                'name' => 'تومان ایران',
                'symbol' => 'تومان',
                'decimals' => 0,
                'is_base' => false,
            ],
            [
                'code' => 'IRR',
                'name' => 'ریال ایران',
                'symbol' => 'ریال',
                'decimals' => 0,
                'is_base' => false, // ارز کارکرد
            ],
            [
                'code' => 'USD',
                'name' => 'دلار آمریکا',
                'symbol' => '$',
                'decimals' => 2,
                'is_base' => false,
            ],
            [
                'code' => 'EUR',
                'name' => 'یورو',
                'symbol' => '€',
                'decimals' => 2,
                'is_base' => false,
            ],
        ];

        foreach ($currencies as $currency) {
            // Check if currency already exists
            $existing = DB::table('currencies')->where('code', $currency['code'])->first();
            
            if (!$existing) {
                DB::table('currencies')->insert(array_merge($currency, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * ایجاد نرخ تبدیل روزانه برای یک سال
     */
    protected function createExchangeRates(): void
    {
        $startDate = Carbon::create($this->year, 1, 1); // فروردین 1
        $endDate = $startDate->copy()->addYear(); // تا پایان سال

        // چک کردن نرخ‌های موجود برای جلوگیری از duplicate
        $existingRates = DB::table('currency_rates')
            ->where('rate_date', '>=', $startDate->format('Y-m-d'))
            ->where('rate_date', '<=', $endDate->format('Y-m-d'))
            ->select('currency_code', 'rate_date')
            ->get()
            ->mapToGroups(function ($item) {
                return [$item->currency_code => $item->rate_date];
            })
            ->map(function ($dates) {
                return $dates->toArray();
            })
            ->toArray();

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
        $rates = [];
        $skippedCount = 0;

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            foreach (['CNY', 'USD', 'EUR'] as $currency) {
                // چک کنیم این نرخ قبلاً وجود داره یا نه
                if (isset($existingRates[$currency]) && in_array($dateStr, $existingRates[$currency])) {
                    $skippedCount++;
                    continue;
                }

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
                    'rate_date' => $dateStr,
                    'source' => 'system',
                    'created_at' => now(),
                ];
            }

            $currentDate->addDay();
        }

        // Bulk insert فقط نرخ‌های جدید
        if (count($rates) > 0) {
            $this->bulkInsert('currency_rates', $rates, 1000);
            $this->info("  ✓ " . count($rates) . " نرخ جدید اضافه شد");
        }
        
        if ($skippedCount > 0) {
            $this->info("  ⊘ {$skippedCount} نرخ موجود skip شد");
        }
    }
}
