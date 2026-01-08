<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    use HasFactory;

    protected $fillable = [
        'cheque_number',
        'bank_id',
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
        'notes',
        'image',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'issue_date' => 'date',
        'due_date' => 'date',
        'cashed_at' => 'datetime',
        'bounced_at' => 'datetime',
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
