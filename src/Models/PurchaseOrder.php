<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number', 'supplier_id', 'store_id', 'order_date', 'expected_delivery_date',
        'delivery_date', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount',
        'currency_code', 'fx_rate_at_order', 'amount_base_at_order', 'status', 'notes',
        'created_by_user_id', 'approved_by_user_id', 'approved_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'fx_rate_at_order' => 'decimal:6',
        'amount_base_at_order' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_CONFIRMED = 'confirmed';
    /** پس از migration پکیج؛ قبل از آن ممکن است در DB نباشد */
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_RECEIVED = 'received';
    const STATUS_INVOICED = 'invoiced';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * وضعیت‌هایی که می‌توان از آن‌ها فاکتور خرید رسمی ساخت (بدون ثبت خودکار دفتر کل).
     *
     * @return list<string>
     */
    public static function statusesEligibleForSupplierInvoice(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }
}
