<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'supplier_invoice_id', 'product_id', 'product_sku', 'product_name',
        'quantity', 'unit_price', 'tax_rate', 'discount_amount', 'total_price',
        'tax_amount', 'shipping_amount', // مبلغ مالیات و سهم حمل هر آیتم
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_price' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }
}
