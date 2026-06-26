<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Credit Note (اعتبار برگشتی)
 * 
 * استاندارد: IFRS 15 - Revenue from Contracts with Customers
 * 
 * زمانی که:
 * - مشتری کالا را برمی‌گرداند
 * - تخفیف بعد از فاکتور داده می‌شود
 * - اصلاح خطا در فاکتور
 */
class CreditNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credit_note_number',
        'customer_id',
        'customer_invoice_id',
        'store_id',
        'credit_date',
        'reason',
        'credit_type',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency_code',
        'fx_rate',
        'amount_base',
        'status',
        'applied_to_invoice_id',
        'applied_at',
        'accounting_document_id',
        'notes',
        'internal_notes',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'fx_rate' => 'decimal:6',
        'amount_base' => 'decimal:2',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_ISSUED = 'issued';
    const STATUS_APPLIED = 'applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_VOID = 'void';

    // Type constants
    const TYPE_RETURN = 'return';      // برگشت کالا
    const TYPE_DISCOUNT = 'discount';  // تخفیف
    const TYPE_CORRECTION = 'correction'; // اصلاح

    // Relations

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function appliedToInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'applied_to_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeApplied($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    // Helper Methods

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApplied(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }
}
