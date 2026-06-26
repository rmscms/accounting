<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Services\Tax\TaxCalculator;
use RMS\Accounting\Models\{CustomerInvoice, SupplierInvoice, VatRemittance};
use RMS\Core\Models\Setting;
use Illuminate\Support\Collection;

/**
 * سرویس مالیات
 * مدیریت و اعمال مالیات به اسناد مالی
 */
class TaxService
{
    /**
     * محاسبه و اعمال مالیات به فاکتور مشتری
     * ⭐ از نرخ ذخیره شده در Invoice استفاده می‌کند (نه از Settings)
     * 
     * @param CustomerInvoice $invoice
     * @return CustomerInvoice
     */
    public function applyVATToCustomerInvoice(CustomerInvoice $invoice, ?string $taxMethodOverride = null): CustomerInvoice
    {
        $invoiceTaxMethod = in_array((string) $taxMethodOverride, ['inclusive', 'exclusive'], true)
            ? (string) $taxMethodOverride
            : (
                in_array((string) ($invoice->tax_method ?? ''), ['inclusive', 'exclusive'], true)
                    ? (string) $invoice->tax_method
                    : tax_calculation_method()
            );
        
        // اگر فاکتور آیتم‌هایی دارد، مالیات هر آیتم را حساب کن
        if ($invoice->items && $invoice->items->count() > 0) {
            $totalTax = 0;
            $totalBase = 0;
            
            foreach ($invoice->items as $item) {
                $taxRate = $item->tax_rate ?? TaxCalculator::getVATRate('standard');
                $lineDiscount = (float) ($item->discount_amount ?? 0);
                $lineNet = max(0, ((float) $item->price * (float) $item->quantity) - $lineDiscount);
                $result = TaxCalculator::calculateVAT(
                    $lineNet,
                    $taxRate, 
                    $invoiceTaxMethod
                );
                
                // آپدیت آیتم
                $item->tax_rate = $result['tax_rate'];
                $item->tax_amount = $result['tax_amount'];
                $item->total = $result['total_amount'];
                $item->save();
                
                $totalTax += $result['tax_amount'];
                $totalBase += $result['base_amount'];
            }
            
            $invoice->subtotal = $totalBase;
            $invoice->tax_amount = $totalTax;
            $invoice->total_amount = $totalBase + $totalTax;
        } else {
            // اگر آیتم ندارد، مالیات کل را حساب کن
            $baseAmount = $this->resolveInvoiceBaseAmount($invoice);
            $result = TaxCalculator::calculateVAT(
                $baseAmount,
                TaxCalculator::getVATRate('standard'),
                $invoiceTaxMethod
            );
            
            $invoice->subtotal = $result['base_amount'];
            $invoice->tax_amount = $result['tax_amount'];
            $invoice->total_amount = $result['total_amount'];
        }
        
        return $invoice;
    }
    
    /**
     * محاسبه و اعمال مالیات به فاکتور تامین‌کننده
     * ⭐ از نرخ ذخیره شده در Invoice استفاده می‌کند (نه از Settings)
     * 
     * @param SupplierInvoice $invoice
     * @return SupplierInvoice
     */
    public function applyVATToSupplierInvoice(SupplierInvoice $invoice, ?string $taxMethodOverride = null): SupplierInvoice
    {
        $invoiceTaxMethod = in_array((string) $taxMethodOverride, ['inclusive', 'exclusive'], true)
            ? (string) $taxMethodOverride
            : (
                in_array((string) ($invoice->tax_method ?? ''), ['inclusive', 'exclusive'], true)
                    ? (string) $invoice->tax_method
                    : tax_calculation_method()
            );
        
        if ($invoice->items && $invoice->items->count() > 0) {
            $totalTax = 0;
            $totalBase = 0;
            
            foreach ($invoice->items as $item) {
                $taxRate = $item->tax_rate ?? TaxCalculator::getVATRate('standard');
                $qty = (float) ($item->quantity ?? 0);
                $unit = (float) ($item->unit_price ?? 0);
                $disc = (float) ($item->discount_amount ?? 0);
                $lineNet = max(0, $qty * $unit - $disc);
                $result = TaxCalculator::calculateVAT(
                    $lineNet,
                    $taxRate,
                    $invoiceTaxMethod
                );
                
                $item->tax_rate = $result['tax_rate'];
                $item->tax_amount = $result['tax_amount'];
                $item->total_price = $result['total_amount'];
                $item->save();
                
                $totalTax += $result['tax_amount'];
                $totalBase += $result['base_amount'];
            }
            
            $invoice->subtotal = $totalBase;
            $invoice->tax_amount = $totalTax;
            $invoice->total_amount = $totalBase + $totalTax;
        } else {
            $baseAmount = $this->resolveInvoiceBaseAmount($invoice);
            $result = TaxCalculator::calculateVAT(
                $baseAmount,
                TaxCalculator::getVATRate('standard'),
                $invoiceTaxMethod
            );
            
            $invoice->subtotal = $result['base_amount'];
            $invoice->tax_amount = $result['tax_amount'];
            $invoice->total_amount = $result['total_amount'];
        }

        $lineDiscountTotal = 0.0;
        if ($invoice->items && $invoice->items->count() > 0) {
            foreach ($invoice->items as $item) {
                $lineDiscountTotal += (float) ($item->discount_amount ?? 0);
            }
        }
        $invoice->discount_amount = $lineDiscountTotal;

        return $invoice;
    }

    private function resolveInvoiceBaseAmount(CustomerInvoice|SupplierInvoice $invoice): float
    {
        if (is_numeric($invoice->subtotal)) {
            return (float) $invoice->subtotal;
        }
        if (is_numeric($invoice->total_amount)) {
            return (float) $invoice->total_amount;
        }

        return 0.0;
    }
    
    /**
     * @deprecated ثبت VAT در همان سند فاکتور انجام می‌شود ({@see CustomerInvoiceService::recordInvoiceInLedger}، {@see SupplierInvoiceService::recordInvoiceInLedger}).
     */
    public function recordTaxInLedger($invoice, LedgerService $ledgerService): void
    {
        // عمداً خالی — از تکرار خط VAT در دفترکل جلوگیری می‌شود.
    }
    
    /**
     * دریافت خلاصه تنظیمات مالیاتی
     * 
     * @return array
     */
    public function getTaxSettings(): array
    {
        return [
            'vat' => [
                'enabled' => Setting::get('accounting.vat.enabled', true),
                'rate' => Setting::get('accounting.vat.rate', 9),
                'rate_reduced' => Setting::get('accounting.vat.rate_reduced', 0),
                'rate_zero' => Setting::get('accounting.vat.rate_zero', 0),
                'method' => function_exists('tax_calculation_method')
                    ? tax_calculation_method()
                    : (Setting::get('accounting.tax.calculation_method')
                        ?: Setting::get('accounting.vat.method')
                        ?: 'exclusive'),
                'account_payable_id' => Setting::get('accounting.vat.account_payable_id'),
                'account_receivable_id' => Setting::get('accounting.vat.account_receivable_id'),
            ],
            'income_tax' => [
                'enabled' => Setting::get('accounting.income_tax.enabled', false),
                'rate' => Setting::get('accounting.income_tax.rate', 25),
                'account_id' => Setting::get('accounting.income_tax.account_id'),
            ],
            'rounding' => Setting::get('accounting.tax.rounding', 'round'),
        ];
    }
    
    /**
     * بررسی اینکه آیا مشتری/محصول از مالیات معاف است
     * 
     * @param mixed $entity
     * @return bool
     */
    public function isExemptFromTax($entity): bool
    {
        // بررسی فیلد tax_exempt در مدل
        if (isset($entity->tax_exempt)) {
            return (bool) $entity->tax_exempt;
        }
        
        // بررسی فیلد is_tax_exempt
        if (isset($entity->is_tax_exempt)) {
            return (bool) $entity->is_tax_exempt;
        }
        
        return false;
    }
    
    /**
     * محاسبه مالیات قابل پرداخت (VAT Payable)
     * مالیات فروش - مالیات خرید
     * 
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function calculateVATPayable(string $startDate, string $endDate): float
    {
        // مالیات فروش (خروجی)
        $outputVAT = CustomerInvoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('tax_amount');
        
        // مالیات خرید (ورودی)
        $inputVAT = SupplierInvoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('tax_amount');

        $remittedVAT = VatRemittance::query()
            ->where('status', VatRemittance::STATUS_POSTED)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');
        
        // مالیات قابل پرداخت خالص = خروجی - ورودی - پرداخت‌های دوره
        return $outputVAT - $inputVAT - $remittedVAT;
    }
}
