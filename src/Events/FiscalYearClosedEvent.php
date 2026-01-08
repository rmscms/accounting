<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\FiscalYear;

/**
 * Event سال مالی بسته شد
 */
class FiscalYearClosedEvent
{
    use Dispatchable, SerializesModels;

    public FiscalYear $fiscalYear;
    public int $closedByUserId;
    public array $balances;
    public array $metadata;

    public function __construct(FiscalYear $fiscalYear, int $closedByUserId, array $balances = [], array $metadata = [])
    {
        $this->fiscalYear = $fiscalYear;
        $this->closedByUserId = $closedByUserId;
        $this->balances = $balances;
        $this->metadata = $metadata;
    }
}
