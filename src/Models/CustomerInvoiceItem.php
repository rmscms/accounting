<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_invoice_id',
        'product_id',
        'product_sku',
        'product_name',
        'quantity',
        'price',
        'tax_rate',
        'discount_amount',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'price' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }
}
