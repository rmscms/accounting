<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BankReconciliationItem extends Model
{
    public const TYPE_OUTSTANDING_CHEQUE = 'outstanding_cheque';
    public const TYPE_DEPOSIT_IN_TRANSIT = 'deposit_in_transit';
    public const TYPE_BANK_CHARGE = 'bank_charge';
    public const TYPE_INTEREST_INCOME = 'interest_income';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const EFFECT_SIDE_BOOK = 'book';
    public const EFFECT_SIDE_BANK = 'bank';

    public const STATE_DRAFT = 'draft';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_POSTED = 'posted';

    protected $fillable = [
        'bank_reconciliation_id',
        'item_type',
        'amount',
        'effect_side',
        'effect_sign',
        'state',
        'reference_type',
        'reference_id',
        'reference_number',
        'reference_date',
        'description',
        'display_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'effect_sign' => 'decimal:2',
        'reference_date' => 'date',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function journalDrafts(): HasMany
    {
        return $this->hasMany(BankReconciliationJournalDraft::class, 'bank_reconciliation_item_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AccountingAttachment::class, 'attachable');
    }
}

