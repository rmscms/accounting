<?php

use RMS\Accounting\Services\Tax\TaxCalculator;
use RMS\Accounting\Models\Currency;
use RMS\Core\Models\Setting;

if (!function_exists('tax_settings')) {
    /**
     * دریافت سریع تنظیمات مالیاتی
     * 
     * @param string|null $key کلید تنظیم (مثلاً 'vat.rate')
     * @param mixed $default مقدار پیش‌فرض
     * @return mixed
     */
    function tax_settings(?string $key = null, $default = null)
    {
        if ($key === null) {
            // بازگشت همه تنظیمات
            return app(\RMS\Accounting\Services\TaxService::class)->getTaxSettings();
        }
        
        return Setting::get("accounting.{$key}", $default);
    }
}

if (!function_exists('calculate_vat')) {
    /**
     * محاسبه سریع مالیات بر ارزش افزوده
     * 
     * @param float $amount
     * @param float|null $taxRate
     * @param string|null $method
     * @return array
     */
    function calculate_vat(float $amount, ?float $taxRate = null, ?string $method = null): array
    {
        return TaxCalculator::calculateVAT($amount, $taxRate, $method);
    }
}

if (!function_exists('calculate_income_tax')) {
    /**
     * محاسبه سریع مالیات بر درآمد
     * 
     * @param float $income
     * @param float|null $taxRate
     * @return array
     */
    function calculate_income_tax(float $income, ?float $taxRate = null): array
    {
        return TaxCalculator::calculateIncomeTax($income, $taxRate);
    }
}

if (!function_exists('format_tax')) {
    /**
     * فرمت کردن مبلغ مالیات
     * 
     * @param float $amount
     * @param bool $withCurrency
     * @return string
     */
    function format_tax(float $amount, bool $withCurrency = true): string
    {
        $formatted = number_format($amount, 0);
        
        if ($withCurrency) {
            $currency = Currency::resolveBaseCurrencyCode('IRR');
            return $formatted . ' ' . $currency;
        }
        
        return $formatted;
    }
}

if (!function_exists('vat_rate')) {
    /**
     * دریافت نرخ VAT
     * 
     * @param string $type 'standard', 'reduced', 'zero'
     * @return float
     */
    function vat_rate(string $type = 'standard'): float
    {
        return TaxCalculator::getVATRate($type);
    }
}

if (!function_exists('tax_calculation_method')) {
    /**
     * روش محاسبهٔ مالیات از تنظیمات ذخیره‌شده در تب مالیات (جدا / شامل).
     * منبع اصلی: accounting.tax.calculation_method — با پشتیبان legacy برای accounting.vat.method
     *
     * @return string 'exclusive'|'inclusive'
     */
    function tax_calculation_method(): string
    {
        $method = Setting::get('accounting.tax.calculation_method');
        if ($method === 'exclusive' || $method === 'inclusive') {
            return $method;
        }
        $legacy = Setting::get('accounting.vat.method');
        if ($legacy === 'exclusive' || $legacy === 'inclusive') {
            return $legacy;
        }

        return 'exclusive';
    }
}

if (!function_exists('is_vat_enabled')) {
    /**
     * بررسی فعال بودن VAT
     * 
     * @return bool
     */
    function is_vat_enabled(): bool
    {
        return (bool) Setting::get('accounting.vat.enabled', true);
    }
}

if (!function_exists('is_income_tax_enabled')) {
    /**
     * بررسی فعال بودن مالیات بر درآمد
     * 
     * @return bool
     */
    function is_income_tax_enabled(): bool
    {
        return (bool) Setting::get('accounting.income_tax.enabled', false);
    }
}
