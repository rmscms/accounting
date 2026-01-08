<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\TaxRate;
use Illuminate\Support\Facades\Cache;

/**
 * سرویس مدیریت مالیات (VAT)
 */
class TaxService
{
    /**
     * محاسبه مالیات
     */
    public function calculateTax(float $amount, ?int $taxRateId = null): array
    {
        $taxRate = $this->getTaxRate($taxRateId);

        if (!$taxRate || $taxRate->rate == 0) {
            return [
                'tax_amount' => 0,
                'tax_rate' => 0,
                'total_with_tax' => $amount,
            ];
        }

        $taxAmount = ($amount * $taxRate->rate) / 100;

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate->rate,
            'total_with_tax' => round($amount + $taxAmount, 2),
        ];
    }

    /**
     * دریافت نرخ مالیات
     */
    public function getTaxRate(?int $taxRateId = null): ?TaxRate
    {
        if ($taxRateId) {
            return TaxRate::find($taxRateId);
        }

        // دریافت نرخ پیش‌فرض
        return TaxRate::where('is_default', true)
            ->where('active', true)
            ->first();
    }

    /**
     * دریافت نرخ مالیات با cache
     */
    public function getCachedDefaultRate(): float
    {
        return Cache::remember('default_tax_rate', now()->addDay(), function () {
            $taxRate = $this->getTaxRate();
            return $taxRate ? $taxRate->rate : 0;
        });
    }

    /**
     * محاسبه مالیات معکوس (از مبلغ شامل مالیات)
     */
    public function calculateReverseTax(float $totalWithTax, ?int $taxRateId = null): array
    {
        $taxRate = $this->getTaxRate($taxRateId);

        if (!$taxRate || $taxRate->rate == 0) {
            return [
                'amount' => $totalWithTax,
                'tax_amount' => 0,
                'tax_rate' => 0,
            ];
        }

        $amount = $totalWithTax / (1 + ($taxRate->rate / 100));
        $taxAmount = $totalWithTax - $amount;

        return [
            'amount' => round($amount, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate->rate,
        ];
    }

    /**
     * دریافت لیست نرخ‌های مالیات فعال
     */
    public function getActiveTaxRates()
    {
        return TaxRate::where('active', true)
            ->orderBy('rate', 'desc')
            ->get();
    }

    /**
     * محاسبه مجموع مالیات پرداختی/دریافتی
     */
    public function getTaxSummary(?string $fromDate = null, ?string $toDate = null, ?int $storeId = null): array
    {
        // محاسبه مالیات از فاکتورهای فروش (پرداختنی)
        $salesTaxQuery = \RMS\Accounting\Models\CustomerInvoice::where('status', 'issued');

        if ($fromDate) {
            $salesTaxQuery->whereDate('invoice_date', '>=', $fromDate);
        }

        if ($toDate) {
            $salesTaxQuery->whereDate('invoice_date', '<=', $toDate);
        }

        if ($storeId) {
            $salesTaxQuery->where('store_id', $storeId);
        }

        $totalSalesTax = $salesTaxQuery->sum('tax_amount');

        // محاسبه مالیات از فاکتورهای خرید (قابل کسر)
        $purchaseTaxQuery = \RMS\Accounting\Models\SupplierInvoice::query();

        if ($fromDate) {
            $purchaseTaxQuery->whereDate('invoice_date', '>=', $fromDate);
        }

        if ($toDate) {
            $purchaseTaxQuery->whereDate('invoice_date', '<=', $toDate);
        }

        if ($storeId) {
            $purchaseTaxQuery->where('store_id', $storeId);
        }

        $totalPurchaseTax = $purchaseTaxQuery->sum('tax_amount');

        return [
            'sales_tax' => $totalSalesTax,
            'purchase_tax' => $totalPurchaseTax,
            'net_tax' => $totalSalesTax - $totalPurchaseTax,
        ];
    }
}
