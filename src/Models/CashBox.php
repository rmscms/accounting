<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBox extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'location',
        'account_id',
        'balance',
        'currency_code',
        'responsible_user_id',
        'active',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'active' => 'boolean',
    ];

    /**
     * Linked Account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Currency
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
