<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\Expense;

/**
 * Event هزینه تایید شد
 */
class ExpenseApprovedEvent
{
    use Dispatchable, SerializesModels;

    public Expense $expense;
    public int $approvedByUserId;
    public array $metadata;

    public function __construct(Expense $expense, int $approvedByUserId, array $metadata = [])
    {
        $this->expense = $expense;
        $this->approvedByUserId = $approvedByUserId;
        $this->metadata = $metadata;
    }
}
