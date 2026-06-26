<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'supplier_invoice_number', 'supplier_id', 'purchase_order_id',
        'store_id', 'invoice_date', 'due_date', 'subtotal', 'tax_amount', 'discount_amount',
        'shipping_amount', 'shipping_allocation_method',
        'total_amount', 'currency_code', 'fx_rate_at_invoice', 'reference_currency_code', 'applied_reference_rate_to_base', 'amount_base_at_invoice',
        'payment_status', 'paid_amount', 'balance_due', 'settlement_mode',
        'paid_at_source_bank_id', 'paid_at_source_cash_box_id', 'paid_at_source_cheque_id', 'paid_at_source_wallet_id',
        'original_invoice_id', 'correction_group_id',
        'notes', 'document_id',
        'tax_method',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'fx_rate_at_invoice' => 'decimal:6',
        'applied_reference_rate_to_base' => 'decimal:6',
        'amount_base_at_invoice' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
    ];

    /**
     * Boot method to register observers
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(\RMS\Accounting\Observers\SupplierInvoiceObserver::class);
    }

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';

    /** تسویه از نوع «نسیه» — بستانکار پرداختنی */
    public const SETTLEMENT_ON_ACCOUNT = 'on_account';

    /** پرداخت نقد در منبع — بستانکار بانک/صندوق بدون پرداختنی */
    public const SETTLEMENT_PAID_AT_SOURCE = 'paid_at_source';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function paidAtSourceBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'paid_at_source_bank_id');
    }

    public function paidAtSourceCashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'paid_at_source_cash_box_id');
    }

    public function paidAtSourceCheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class, 'paid_at_source_cheque_id');
    }

    public function paidAtSourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'paid_at_source_wallet_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function replacementInvoices(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id');
    }

    public function correctionLogs(): HasMany
    {
        return $this->hasMany(SupplierInvoiceCorrection::class);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('payment_status', '!=', self::STATUS_PAID);
    }

    /**
     * شمارهٔ پیشنهادی فاکتور خرید داخلی (SINV-…).
     */
    public static function suggestNextInvoiceNumber(): string
    {
        $next = ((int) static::query()->max('id')) + 1;
        $code = 'SINV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $guard = 0;
        while (static::query()->where('invoice_number', $code)->exists() && $guard < 100) {
            $next++;
            $code = 'SINV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $guard++;
        }

        return $code;
    }
}
