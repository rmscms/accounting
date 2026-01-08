<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'branch_name',
        'account_number',
        'iban',
        'swift_code',
        'account_id',
        'balance',
        'currency_code',
        'active',
        'notes',
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
     * POS Terminals
     */
    public function posTerminals(): HasMany
    {
        return $this->hasMany(POSTerminal::class);
    }

    /**
     * Cheques
     */
    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
