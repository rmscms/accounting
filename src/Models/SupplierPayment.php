<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (SupplierPayment $payment): void {
            $fx = (float) ($payment->fx_rate_at_payment ?? 0);
            if ($fx <= 0) {
                $payment->fx_rate_at_payment = 1;
                $fx = 1.0;
            }

            $cc = trim((string) ($payment->currency_code ?? ''));
            if ($cc === '') {
                $payment->currency_code = Currency::resolveBaseCurrencyCode('IRR');
            }

            $attrs = $payment->getAttributes();
            $rawBase = $attrs['amount_base_at_payment'] ?? null;
            if ($rawBase === null || $rawBase === '') {
                $amount = (float) ($payment->amount ?? 0);
                $payment->amount_base_at_payment = round($amount * $fx, 4);
            }
        });
    }

    protected $fillable = [
        'payment_number', 'supplier_id', 'supplier_invoice_id', 'purchase_order_id', 'payment_method_id',
        'amount', 'currency_code', 'fx_rate_at_payment', 'amount_base_at_payment',
        'fx_difference_irr', 'payment_date', 'bank_id', 'cash_box_id', 'cheque_id', 'wallet_id',
        'status', 'notes', 'receipt_image', 'document_id',
        'processed_by_admin_id', 'processed_by_user_id', 'processed_at',
        'original_supplier_payment_id', 'correction_group_id', 'correction_reason',
        'corrected_by_admin_id', 'corrected_by_user_id', 'corrected_at', 'correction_document_id',
        'voided_at', 'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fx_rate_at_payment' => 'decimal:6',
        'amount_base_at_payment' => 'decimal:4',
        'fx_difference_irr' => 'decimal:4',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
        'corrected_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REVERSED = 'reversed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_VOIDED = 'voided';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /** @deprecated Use supplierInvoice(); retained for callers using ->invoice */
    public function invoice(): BelongsTo
    {
        return $this->supplierInvoice();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function processedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'processed_by_admin_id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by_user_id');
    }

    public function correctedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'corrected_by_admin_id');
    }

    public function correctedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'corrected_by_user_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
