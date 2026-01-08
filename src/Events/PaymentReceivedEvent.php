<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\CustomerPayment;

/**
 * Event دریافت از مشتری ثبت شد
 */
class PaymentReceivedEvent
{
    use Dispatchable, SerializesModels;

    public CustomerPayment $payment;
    public array $metadata;

    public function __construct(CustomerPayment $payment, array $metadata = [])
    {
        $this->payment = $payment;
        $this->metadata = $metadata;
    }
}
