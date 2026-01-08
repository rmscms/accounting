<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'currency_code',
        'rate_to_irr',
        'rate_date',
        'source',
        'created_by_user_id',
    ];

    protected $casts = [
        'rate_to_irr' => 'decimal:6',
        'rate_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Currency
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Scope: By date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('rate_date', $date);
    }

    /**
     * Scope: Latest rates
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('rate_date', 'desc');
    }
}
