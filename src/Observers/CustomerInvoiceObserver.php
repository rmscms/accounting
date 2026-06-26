<?php

namespace RMS\Accounting\Observers;

use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Services\TaxService;

/**
 * Observer برای محاسبه خودکار مالیات فاکتورهای مشتری (بدون ثبت جداگانه در دفترکل؛ سند در CustomerInvoiceService ثبت می‌شود).
 */
class CustomerInvoiceObserver
{
    public function __construct(
        protected TaxService $taxService
    ) {
    }

    public function saving(CustomerInvoice $invoice): void
    {
        if (! is_vat_enabled()) {
            return;
        }

        if ($invoice->customer && $this->taxService->isExemptFromTax($invoice->customer)) {
            $invoice->tax_amount = 0;

            return;
        }

        if ($invoice->exists && $invoice->items()->exists()) {
            return;
        }

        $this->taxService->applyVATToCustomerInvoice($invoice);
    }
}
