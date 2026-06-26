<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BankReconciliation extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'bank_id',
        'gl_account_id',
        'statement_date',
        'book_balance',
        'bank_statement_balance',
        'adjusted_book_balance',
        'adjusted_bank_balance',
        'difference_amount',
        'status',
        'is_balanced',
        'finalized_at',
        'finalized_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'book_balance' => 'decimal:4',
        'bank_statement_balance' => 'decimal:4',
        'adjusted_book_balance' => 'decimal:4',
        'adjusted_bank_balance' => 'decimal:4',
        'difference_amount' => 'decimal:4',
        'is_balanced' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class)->orderBy('display_order')->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AccountingAttachment::class, 'attachable');
    }
}

