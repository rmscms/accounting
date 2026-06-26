<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualJournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'manual_journal_id',
        'line_number',
        'account_id',
        'debit_amount',
        'credit_amount',
        'currency_code',
        'fx_rate',
        'description',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
    ];

    /**
     * سند دستی
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'manual_journal_id');
    }

    /**
     * حساب
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * ارز
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
