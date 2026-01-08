<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\CurrencyRate;
use Illuminate\Support\Facades\Http;

/**
 * دستور بروزرسانی نرخ ارز
 */
class UpdateExchangeRatesCommand extends Command
{
    protected $signature = 'accounting:update-exchange-rates 
                            {--source=cbi : منبع نرخ ارز (cbi, bonbast, custom)}';

    protected $description = 'بروزرسانی خودکار نرخ ارز از منابع آنلاین';

    public function handle()
    {
        $source = $this->option('source');

        $this->info("💱 بروزرسانی نرخ ارز از منبع: {$source}");

        $currencies = Currency::where('active', true)
            ->where('code', '!=', 'IRR')
            ->get();

        if ($currencies->isEmpty()) {
            $this->warn('⚠️ هیچ ارزی برای بروزرسانی یافت نشد.');
            return 0;
        }

        $updatedCount = 0;
        $failedCount = 0;

        foreach ($currencies as $currency) {
            try {
                $rate = $this->fetchExchangeRate($currency->code, $source);

                if ($rate > 0) {
                    CurrencyRate::create([
                        'currency_code' => $currency->code,
                        'rate_to_irr' => $rate,
                        'rate_date' => now()->toDateString(),
                        'source' => $source,
                    ]);

                    $this->line("✅ {$currency->code}: " . number_format($rate) . " ریال");
                    $updatedCount++;
                } else {
                    throw new \Exception('نرخ نامعتبر');
                }
            } catch (\Exception $e) {
                $this->error("❌ {$currency->code}: {$e->getMessage()}");
                $failedCount++;
            }
        }

        $this->info('');
        $this->info("✅ بروزرسانی کامل شد: {$updatedCount} موفق، {$failedCount} ناموفق");

        return 0;
    }

    /**
     * دریافت نرخ ارز از منبع
     */
    protected function fetchExchangeRate(string $currencyCode, string $source): float
    {
        return match ($source) {
            'cbi' => $this->fetchFromCBI($currencyCode),
            'bonbast' => $this->fetchFromBonbast($currencyCode),
            'custom' => $this->fetchFromCustomAPI($currencyCode),
            default => 0,
        };
    }

    /**
     * دریافت از بانک مرکزی
     */
    protected function fetchFromCBI(string $currencyCode): float
    {
        // پیاده‌سازی API بانک مرکزی
        // مثال: برای تست نرخ‌های ثابت
        return match ($currencyCode) {
            'USD' => 550000,
            'EUR' => 600000,
            'AED' => 150000,
            'TRY' => 18000,
            default => 0,
        };
    }

    /**
     * دریافت از بن‌بست
     */
    protected function fetchFromBonbast(string $currencyCode): float
    {
        // پیاده‌سازی API بن‌بست در نسخه بعدی
        return 0;
    }

    /**
     * دریافت از API سفارشی
     */
    protected function fetchFromCustomAPI(string $currencyCode): float
    {
        // دریافت از API سفارشی پروژه
        return 0;
    }
}
