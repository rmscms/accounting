<?php

namespace RMS\Accounting\Console\Simulators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * کلاس پایه برای شبیه‌سازی‌های حسابداری
 */
abstract class BaseSimulator
{
    protected Command $command;
    protected int $year;
    protected array $options;
    protected Carbon $startDate;
    protected Carbon $endDate;
    
    public function __construct(Command $command, int $year, array $options = [])
    {
        $this->command = $command;
        $this->year = $year;
        $this->options = $options;
        
        // تبدیل سال شمسی به میلادی برای محاسبات
        $this->startDate = Carbon::parse("{$year}-01-01"); // فروردین 1
        $this->endDate = Carbon::parse("{$year}-12-29"); // اسفند 29
    }
    
    /**
     * اجرای شبیه‌سازی
     */
    abstract public function simulate(): void;
    
    /**
     * نمایش پیغام info
     */
    protected function info(string $message): void
    {
        $this->command->info($message);
    }
    
    /**
     * نمایش پیغام success
     */
    protected function success(string $message): void
    {
        $this->command->info("  ✅ {$message}");
    }
    
    /**
     * نمایش پیغام error
     */
    protected function error(string $message): void
    {
        $this->command->error("  ❌ {$message}");
    }
    
    /**
     * نمایش پیغام warning
     */
    protected function warn(string $message): void
    {
        $this->command->warn("  ⚠️  {$message}");
    }
    
    /**
     * ایجاد progress bar
     */
    protected function createProgressBar(int $max): \Symfony\Component\Console\Helper\ProgressBar
    {
        return $this->command->getOutput()->createProgressBar($max);
    }
    
    /**
     * تولید عدد تصادفی با توزیع نرمال
     */
    protected function randomNormal(float $mean, float $stdDev): float
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        
        $z = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
        
        return $mean + $stdDev * $z;
    }
    
    /**
     * انتخاب تصادفی با وزن‌دهی
     */
    protected function weightedRandom(array $items, array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(0, $totalWeight * 1000) / 1000;
        
        $cumulative = 0;
        foreach ($items as $index => $item) {
            $cumulative += $weights[$index];
            if ($random <= $cumulative) {
                return $item;
            }
        }
        
        return end($items);
    }
    
    /**
     * تولید مبلغ تصادفی در بازه مشخص
     */
    protected function randomAmount(int $min, int $max): int
    {
        // گرد کردن به نزدیک‌ترین 10000 تومان
        $amount = mt_rand($min, $max);
        return (int) (round($amount / 10000) * 10000);
    }
    
    /**
     * دریافت تاریخ تصادفی در بازه
     */
    protected function randomDate(Carbon $start, Carbon $end): Carbon
    {
        $diff = $end->diffInDays($start);
        $randomDays = mt_rand(0, $diff);
        
        return $start->copy()->addDays($randomDays);
    }
    
    /**
     * Bulk Insert با بهینه‌سازی
     */
    protected function bulkInsert(string $table, array $data, int $chunkSize = 500): void
    {
        $chunks = array_chunk($data, $chunkSize);
        
        DB::transaction(function () use ($table, $chunks) {
            foreach ($chunks as $chunk) {
                DB::table($table)->insert($chunk);
            }
        });
    }
    
    /**
     * محاسبه مالیات بر ارزش افزوده
     */
    protected function calculateVAT(float $amount, float $rate = 0.09): float
    {
        return round($amount * $rate);
    }
    
    /**
     * تولید شماره یکتا
     */
    protected function generateUniqueNumber(string $prefix, int $length = 10): string
    {
        $timestamp = now()->format('ymdHis');
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . substr($timestamp . $random, 0, $length);
    }
    
    /**
     * دریافت option
     */
    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
    
    /**
     * تولید تاریخ شمسی
     */
    protected function toPersianDate(Carbon $date): string
    {
        // تبدیل ساده - در production باید از کتابخانه jdf استفاده شود
        return $date->format('Y-m-d');
    }
    
    /**
     * Format کردن عدد با separator
     */
    protected function formatNumber($number): string
    {
        return number_format($number, 0);
    }
    
    /**
     * تبدیل به میلیارد
     */
    protected function toBillion($number): string
    {
        return number_format($number / 1_000_000_000, 2) . 'B';
    }
    
    /**
     * نمایش آمار
     */
    protected function displayStats(array $stats): void
    {
        foreach ($stats as $key => $value) {
            if (is_numeric($value) && $value > 1000000) {
                $this->info("    {$key}: " . $this->formatNumber($value));
            } else {
                $this->info("    {$key}: {$value}");
            }
        }
    }
}
