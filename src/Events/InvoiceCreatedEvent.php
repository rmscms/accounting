<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\CustomerInvoice;

/**
 * Event فاکتور فروش ایجاد شد
 * برای integration با shop و سایر پکیج‌ها
 */
class InvoiceCreatedEvent
{
    use Dispatchable, SerializesModels;

    public CustomerInvoice $invoice;
    public array $metadata;

    public function __construct(CustomerInvoice $invoice, array $metadata = [])
    {
        $this->invoice = $invoice;
        $this->metadata = $metadata;
    }
}
