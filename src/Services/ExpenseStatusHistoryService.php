<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseStatusHistory;

class ExpenseStatusHistoryService
{
    public function record(
        Expense $expense,
        ?string $fromStatus,
        string $toStatus,
        ?int $adminUserId,
        ?string $note = null
    ): ExpenseStatusHistory {
        return ExpenseStatusHistory::query()->create([
            'expense_id' => $expense->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'admin_user_id' => $adminUserId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
