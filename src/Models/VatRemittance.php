<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatRemittance extends Model
{
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'period_start',
        'period_end',
        'payment_date',
        'amount',
        'bank_id',
        'cash_box_id',
        'wallet_id',
        'accounting_document_id',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
