<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'credit_note_id',
        'product_id',
        'product_sku',
        'product_name',
        'quantity',
        'price',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total',
        'return_reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }
}
