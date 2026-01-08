<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_number', 'settlement_type', 'party_type', 'party_id', 'store_id',
        'total_invoices', 'total_payments', 'settlement_amount', 'settlement_date',
        'currency_code', 'status', 'notes', 'document_id',
        'created_by_user_id', 'approved_by_user_id', 'approved_at',
    ];

    protected $casts = [
        'total_invoices' => 'decimal:4',
        'total_payments' => 'decimal:4',
        'settlement_amount' => 'decimal:4',
        'settlement_date' => 'date',
        'approved_at' => 'datetime',
    ];

    const TYPE_CUSTOMER = 'customer';
    const TYPE_SUPPLIER = 'supplier';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('party_type', self::TYPE_CUSTOMER)
            ->where('party_id', $customerId);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('party_type', self::TYPE_SUPPLIER)
            ->where('party_id', $supplierId);
    }
}
