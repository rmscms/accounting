<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Cheque $cheque) {
            if ($cheque->currency_code === null || $cheque->currency_code === '') {
                $cheque->currency_code = Currency::resolveBaseCurrencyCode('IRR');
            }
        });
    }

    protected $fillable = [
        'cheque_number',
        'bank_id',
        'party_id',
        'chequebook_id',
        'cheque_type',
        'amount',
        'currency_code',
        'issue_date',
        'due_date',
        'payer_name',
        'payer_account',
        'payee_name',
        'payee_account',
        'status',
        'cashed_at',
        'bounced_at',
        'bounce_reason',
        'payment_id',
        'accounting_document_id',
        'source_type',
        'source_id',
        'notes',
        'meta_json',
        'image',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'issue_date' => 'date',
        'due_date' => 'date',
        'cashed_at' => 'datetime',
        'bounced_at' => 'datetime',
        'meta_json' => 'array',
    ];

    const TYPE_RECEIVED = 'received';
    const TYPE_ISSUED = 'issued';

    const STATUS_ISSUED = 'issued';
    const STATUS_PENDING = 'pending';
    const STATUS_CASHED = 'cashed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_CANCELLED = 'cancelled';

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function chequebook(): BelongsTo
    {
        return $this->belongsTo(Chequebook::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('due_date', '<=', now()->addDays($days))
            ->where('status', self::STATUS_PENDING);
    }
}
