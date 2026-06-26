<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'customer_id', 'store_id', 'invoice_date', 'due_date',
        'subtotal', 'tax_amount', 'discount_amount', 'invoice_discount_amount', 'shipping_amount', 'shipping_charged_to_customer', 'total_amount',
        'currency_code', 'fx_rate', 'reference_currency_code', 'applied_reference_rate_to_base', 'amount_base', 'payment_status', 'status',
        'paid_amount', 'balance_due', 'reference_type', 'reference_id', 'notes',
        'document_id', 'settlement_mode', 'upfront_payment_amount',
        'original_invoice_id', 'correction_group_id',
        'paid_at_source_bank_id', 'paid_at_source_cash_box_id', 'paid_at_source_cheque_id', 'paid_at_source_wallet_id',
        'tax_method',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'invoice_discount_amount' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'shipping_charged_to_customer' => 'boolean',
        'total_amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'applied_reference_rate_to_base' => 'decimal:6',
        'amount_base' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
        'upfront_payment_amount' => 'decimal:4',
    ];

    /**
     * Boot method to register observers
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(\RMS\Accounting\Observers\CustomerInvoiceObserver::class);
    }

    // Invoice Status
    const STATUS_DRAFT = 'draft';
    const STATUS_ISSUED = 'issued';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_VOID = 'void';

    // Payment Status
    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';

    // Settlement Mode
    const SETTLEMENT_CREDIT = 'credit';
    const SETTLEMENT_CASH = 'cash';
    const SETTLEMENT_MIXED = 'mixed';

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerInvoiceItem::class, 'customer_invoice_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
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

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function replacementInvoices(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id');
    }

    public function canEditHeader(): bool
    {
        return ! (int) ($this->document_id ?? 0);
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

    public static function suggestNextInvoiceNumber(): string
    {
        $prefix = 'CINV-'.now()->format('Ymd').'-';
        $last = self::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
