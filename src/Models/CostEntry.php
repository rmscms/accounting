<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEntry extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'accounting_document_id', 'cost_type', 'reference_type', 'reference_id',
        'product_id', 'product_sku', 'quantity', 'unit_cost', 'total_cost',
        'currency_code', 'fx_rate', 'cost_irr', 'cost_method', 'notes',
        'source_supplier_id', 'source_supplier_invoice_id', 'source_purchase_invoice_id',
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

    /**
     * Source Supplier relationship
     */
    public function sourceSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'source_supplier_id');
    }

    /**
     * Source Supplier Invoice relationship
     */
    public function sourceSupplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'source_supplier_invoice_id');
    }

    /**
     * Get source supplier (helper method)
     */
    public function getSourceSupplier(): ?Supplier
    {
        return $this->sourceSupplier;
    }

    /**
     * Get source supplier invoice (helper method)
     */
    public function getSourceSupplierInvoice(): ?SupplierInvoice
    {
        return $this->sourceSupplierInvoice;
    }
}
