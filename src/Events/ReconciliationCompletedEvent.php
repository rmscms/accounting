<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\PaymentReconciliation;

/**
 * Event تطبیق پرداخت تایید شد
 */
class ReconciliationCompletedEvent
{
    use Dispatchable, SerializesModels;

    public PaymentReconciliation $reconciliation;
    public array $metadata;

    public function __construct(PaymentReconciliation $reconciliation, array $metadata = [])
    {
        $this->reconciliation = $reconciliation;
        $this->metadata = $metadata;
    }
}
