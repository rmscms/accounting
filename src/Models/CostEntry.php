<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostEntry extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'accounting_document_id', 'cost_type', 'reference_type', 'reference_id',
        'product_id', 'product_sku', 'quantity', 'unit_cost', 'total_cost',
        'currency_code', 'fx_rate', 'cost_irr', 'cost_method', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'cost_irr' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    const TYPE_PURCHASE = 'purchase';
    const TYPE_MANUFACTURING = 'manufacturing';
    const TYPE_OVERHEAD = 'overhead';
    const TYPE_ADJUSTMENT = 'adjustment';

    const METHOD_FIFO = 'FIFO';
    const METHOD_LIFO = 'LIFO';
    const METHOD_AVG = 'AVG';
}
