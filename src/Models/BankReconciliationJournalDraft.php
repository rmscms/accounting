<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationJournalDraft extends Model
{
    protected $fillable = [
        'bank_reconciliation_item_id',
        'journal_payload_json',
        'manual_journal_id',
        'posted_at',
    ];

    protected $casts = [
        'journal_payload_json' => 'array',
        'posted_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationItem::class, 'bank_reconciliation_item_id');
    }

    public function manualJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'manual_journal_id');
    }
}

