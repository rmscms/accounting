<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'wallet_type',
        'balance',
        'currency_code',
        'account_id',
        'active',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'active' => 'boolean',
    ];

    const TYPE_CUSTOMER = 'customer';
    const TYPE_SUPPLIER = 'supplier';
    const TYPE_EMPLOYEE = 'employee';
    const TYPE_TREASURY = 'treasury';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePartyWallets($query)
    {
        return $query->whereIn('wallet_type', [
            self::TYPE_CUSTOMER,
            self::TYPE_SUPPLIER,
            self::TYPE_EMPLOYEE,
        ]);
    }

    public function scopeTreasury($query)
    {
        return $query->where('wallet_type', self::TYPE_TREASURY);
    }
}
