<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer Refund (بازگشت وجه به مشتری)
 * 
 * زمانی که پول را به مشتری پس می‌دهیم
 * معمولاً بعد از Credit Note
 */
class CustomerRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'refund_number',
        'customer_id',
        'credit_note_id',
        'customer_payment_id',
        'store_id',
        'refund_date',
        'reason',
        'amount',
        'currency_code',
        'fx_rate',
        'amount_base',
        'refund_method',
        'bank_id',
        'cash_box_id',
        'status',
        'processed_at',
        'reference_number',
        'accounting_document_id',
        'original_refund_id',
        'correction_group_id',
        'correction_reason',
        'corrected_by_user_id',
        'corrected_at',
        'correction_document_id',
        'notes',
        'created_by_admin_id',
        'created_by_user_id',
        'approved_by_admin_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
        'fx_rate' => 'decimal:6',
        'amount_base' => 'decimal:2',
        'processed_at' => 'datetime',
        'corrected_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';
    const STATUS_CANCELLED = 'cancelled';

    // Method constants
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHEQUE = 'cheque';
    const METHOD_ONLINE = 'online';
    const METHOD_DEDUCT = 'deduct_from_next_invoice';

    // Relations

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'created_by_admin_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'approved_by_admin_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    // Helper Methods

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
