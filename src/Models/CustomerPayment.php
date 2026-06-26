<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number', 'customer_id', 'store_id', 'customer_invoice_id',
        'payment_method_id', 'amount', 'currency_code', 'fx_rate', 'amount_base',
        'payment_date', 'bank_id', 'cash_box_id', 'pos_terminal_id', 'wallet_id',
        'cheque_id', 'gateway_transaction_id', 'status', 'notes', 'receipt_image',
        'document_id', 'processed_by_admin_id', 'processed_by_user_id', 'processed_at',
        'original_payment_id', 'correction_group_id', 'correction_reason',
        'corrected_by_admin_id', 'corrected_by_user_id', 'corrected_at', 'correction_document_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'amount_base' => 'decimal:4',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REVERSED = 'reversed';
    const STATUS_CANCELLED = 'cancelled';

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
