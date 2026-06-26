<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_adjustment_id',
        'line_number',
        'product_id',
        'product_type',
        'product_name',
        'sku',
        'system_quantity',
        'actual_quantity',
        'difference_quantity',
        'unit_cost',
        'adjustment_value',
        'reason',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'system_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'difference_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'adjustment_value' => 'decimal:4',
    ];

    /**
     * سند تعدیل
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    /**
     * محصول (polymorphic)
     */
    public function product()
    {
        if ($this->product_id && $this->product_type) {
            return $this->morphTo('product', 'product_type', 'product_id');
        }
        return null;
    }
}
