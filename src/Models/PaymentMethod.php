<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'requires_bank',
        'requires_pos',
        'requires_gateway',
        'account_id',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'requires_bank' => 'boolean',
        'requires_pos' => 'boolean',
        'requires_gateway' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Types
    const TYPE_CASH = 'cash';
    const TYPE_POS = 'pos';
    const TYPE_ONLINE = 'online';
    const TYPE_CHEQUE = 'cheque';
    const TYPE_CARD_TRANSFER = 'card_transfer';
    const TYPE_WALLET = 'wallet';

    /**
     * Linked Account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
