<?php

namespace RMS\Accounting\Services\Tax;

use RMS\Core\Models\Setting;

/**
 * کلاس محاسبه‌گر مالیات
 * انجام محاسبات مالیاتی بر اساس تنظیمات
 */
class TaxCalculator
{
    /**
     * محاسبه مالیات بر ارزش افزوده (VAT)
     * 
     * @param float $amount مبلغ پایه
     * @param float|null $taxRate نرخ مالیات (اگر null باشد از تنظیمات می‌خواند)
     * @param string|null $method روش محاسبه (exclusive/inclusive) - اگر null باشد از تنظیمات می‌خواند
     * @return array ['tax_amount' => float, 'total_amount' => float, 'base_amount' => float]
     */
    public static function calculateVAT(float $amount, ?float $taxRate = null, ?string $method = null): array
    {
        // اگر VAT غیرفعال است
        if (!Setting::get('accounting.vat.enabled', true)) {
            return [
                'tax_amount' => 0,
                'total_amount' => $amount,
                'base_amount' => $amount,
                'tax_rate' => 0,
            ];
        }
        
        // دریافت نرخ مالیات (از پارامتر یا تنظیمات)
        $rate = $taxRate ?? Setting::get('accounting.vat.rate', 9);
        
        // دریافت روش محاسبه (از پارامتر یا تنظیمات؛ هم‌تراز با tax_calculation_method())
        if ($method === null || $method === '') {
            $calculationMethod = function_exists('tax_calculation_method')
                ? tax_calculation_method()
                : (Setting::get('accounting.tax.calculation_method')
                    ?: Setting::get('accounting.vat.method')
                    ?: 'exclusive');
        } else {
            $calculationMethod = $method;
        }
        
        $taxAmount = 0;
        $totalAmount = 0;
        $baseAmount = $amount;
        
        if ($calculationMethod === 'inclusive') {
            // قیمت شامل مالیات است - باید مالیات را استخراج کنیم
            // فرمول: tax = amount - (amount / (1 + rate/100))
            $baseAmount = $amount / (1 + ($rate / 100));
            $taxAmount = $amount - $baseAmount;
            $totalAmount = $amount;
        } else {
            // قیمت بدون مالیات - باید مالیات را اضافه کنیم
            // فرمول: tax = amount * (rate/100)
            $taxAmount = $amount * ($rate / 100);
            $totalAmount = $amount + $taxAmount;
        }
        
        // اعمال گرد کردن
        $taxAmount = self::applyRounding($taxAmount);
        $totalAmount = self::applyRounding($totalAmount);
        $baseAmount = self::applyRounding($baseAmount);
        
        return [
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_amount' => $baseAmount,
            'tax_rate' => $rate,
            'method' => $calculationMethod,
        ];
    }
    
    /**
     * محاسبه مالیات بر درآمد
     * 
     * @param float $income درآمد خالص
     * @param float|null $taxRate نرخ مالیات (اگر null باشد از تنظیمات می‌خواند)
     * @return array
     */
    public static function calculateIncomeTax(float $income, ?float $taxRate = null): array
    {
        // اگر مالیات بر درآمد غیرفعال است
        if (!Setting::get('accounting.income_tax.enabled', false)) {
            return [
                'tax_amount' => 0,
                'net_income' => $income,
                'tax_rate' => 0,
            ];
        }
        
        // دریافت نرخ مالیات
        $rate = $taxRate ?? Setting::get('accounting.income_tax.rate', 25);
        
        // محاسبه مالیات
        $taxAmount = $income * ($rate / 100);
        $taxAmount = self::applyRounding($taxAmount);
        
        return [
            'tax_amount' => $taxAmount,
            'net_income' => $income - $taxAmount,
            'tax_rate' => $rate,
        ];
    }
    
    /**
     * اعمال گرد کردن بر اساس تنظیمات
     * 
     * @param float $amount
     * @return float
     */
    public static function applyRounding(float $amount): float
    {
        $method = Setting::get('accounting.tax.rounding', 'round');
        $decimalPlaces = (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0));
        $decimalPlaces = min(4, max(0, $decimalPlaces));
        
        switch ($method) {
            case 'ceil':
                return ceil($amount);
            case 'floor':
                return floor($amount);
            case 'round':
            default:
                return round($amount, $decimalPlaces);
        }
    }
    
    /**
     * دریافت نرخ VAT بر اساس نوع
     * 
     * @param string $type 'standard', 'reduced', 'zero'
     * @return float
     */
    public static function getVATRate(string $type = 'standard'): float
    {
        switch ($type) {
            case 'reduced':
                return (float) Setting::get('accounting.vat.rate_reduced', 0);
            case 'zero':
                return (float) Setting::get('accounting.vat.rate_zero', 0);
            case 'standard':
            default:
                return (float) Setting::get('accounting.vat.rate', 9);
        }
    }
    
    /**
     * بررسی معاف بودن از مالیات
     * 
     * @param float $taxRate
     * @return bool
     */
    public static function isExempt(float $taxRate): bool
    {
        return $taxRate <= 0;
    }
    
    /**
     * محاسبه مالیات برای چند آیتم
     * 
     * @param array $items [['amount' => float, 'tax_rate' => float], ...]
     * @return array
     */
    public static function calculateMultipleItems(array $items): array
    {
        $totalBase = 0;
        $totalTax = 0;
        $totalAmount = 0;
        
        $calculatedItems = [];
        
        foreach ($items as $item) {
            $amount = $item['amount'] ?? 0;
            $taxRate = $item['tax_rate'] ?? null;
            
            $result = self::calculateVAT($amount, $taxRate);
            
            $calculatedItems[] = array_merge($item, $result);
            
            $totalBase += $result['base_amount'];
            $totalTax += $result['tax_amount'];
            $totalAmount += $result['total_amount'];
        }
        
        return [
            'items' => $calculatedItems,
            'totals' => [
                'base_amount' => self::applyRounding($totalBase),
                'tax_amount' => self::applyRounding($totalTax),
                'total_amount' => self::applyRounding($totalAmount),
            ],
        ];
    }
}
