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
        'payment_method_id', 'amount', 'currency_code', 'fx_rate', 'amount_irr',
        'payment_date', 'bank_id', 'cash_box_id', 'pos_terminal_id', 'wallet_id',
        'cheque_id', 'gateway_transaction_id', 'status', 'notes', 'receipt_image',
        'document_id', 'processed_by_user_id', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'amount_irr' => 'decimal:4',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
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

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
