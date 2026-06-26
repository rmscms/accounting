<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'product_sku', 'product_name',
        'quantity', 'received_quantity', 'unit_price', 'tax_rate', 'discount_amount', 'total_price', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'total_price' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
