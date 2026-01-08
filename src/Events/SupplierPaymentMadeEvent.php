<?php

namespace RMS\Accounting\Events;

use RMS\Accounting\Models\SupplierPayment;

class SupplierPaymentMadeEvent
{
    public SupplierPayment $payment;

    public function __construct(SupplierPayment $payment)
    {
        $this->payment = $payment;
    }
}
