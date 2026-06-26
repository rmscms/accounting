<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareholderWithdrawal extends Model
{
    public const SOURCE_BANK = 'bank';

    public const SOURCE_CASH = 'cash';

    protected $fillable = [
        'shareholder_id',
        'amount',
        'currency_code',
        'journal_date',
        'source_type',
        'bank_id',
        'cash_box_id',
        'description',
        'manual_journal_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function manualJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class);
    }
}
