<?php

namespace RMS\Accounting\Observers;

use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Services\TaxService;

/**
 * Observer برای محاسبه خودکار مالیات فاکتورهای تأمین‌کننده (بدون ثبت جداگانه در دفترکل؛ سند در SupplierInvoiceService ثبت می‌شود).
 */
class SupplierInvoiceObserver
{
    public function __construct(
        protected TaxService $taxService
    ) {
    }

    public function saving(SupplierInvoice $invoice): void
    {
        if (! is_vat_enabled()) {
            return;
        }

        if ($invoice->supplier && $this->taxService->isExemptFromTax($invoice->supplier)) {
            $invoice->tax_amount = 0;

            return;
        }

        if ($invoice->exists && $invoice->items()->exists()) {
            return;
        }

        $this->taxService->applyVATToSupplierInvoice($invoice);
    }
}
