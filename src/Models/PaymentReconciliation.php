<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_number', 'payment_id', 'reconciliation_type',
        'bank_id', 'cash_box_id', 'pos_terminal_id',
        'expected_amount', 'actual_amount', 'discrepancy_amount',
        'reconciliation_date', 'is_reconciled', 'reconciled_by_user_id', 'reconciled_at',
        'bank_statement_reference', 'receipt_image', 'discrepancy_notes', 'status',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:4',
        'actual_amount' => 'decimal:4',
        'discrepancy_amount' => 'decimal:4',
        'reconciliation_date' => 'date',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    const TYPE_BANK = 'bank';
    const TYPE_CASH_BOX = 'cash_box';
    const TYPE_POS = 'pos';
    const TYPE_GENERAL = 'general';

    const STATUS_PENDING = 'pending';
    const STATUS_MATCHED = 'matched';
    const STATUS_DISCREPANCY = 'discrepancy';
    const STATUS_RESOLVED = 'resolved';

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(POSTerminal::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeWithDiscrepancy($query)
    {
        return $query->where('status', self::STATUS_DISCREPANCY);
    }

    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }
}
