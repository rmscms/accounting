<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialLedger extends Model
{
    use HasFactory;

    // این جدول IMMUTABLE است - فقط INSERT
    protected $fillable = [
        'event_type',
        'event_source',
        'source_reference_type',
        'source_reference_id',
        'store_id',
        'account_id',
        'currency_code',
        'debit_amount',
        'credit_amount',
        'fx_rate_to_irr',
        'amount_irr',
        'accounting_document_id',
        'description',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'source_reference_id' => 'integer',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'fx_rate_to_irr' => 'decimal:6',
        'amount_irr' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    // فقط created_at داریم - updated_at نداریم
    const UPDATED_AT = null;

    // Event Types
    const EVENT_SALE = 'SALE';
    const EVENT_PURCHASE = 'PURCHASE';
    const EVENT_PAYMENT = 'PAYMENT';
    const EVENT_RECEIPT = 'RECEIPT';
    const EVENT_FX_DIFF = 'FX_DIFF';
    const EVENT_TAX = 'TAX';
    const EVENT_COST = 'COST';
    const EVENT_ADJUSTMENT = 'ADJUSTMENT';
    const EVENT_REVERSAL = 'REVERSAL';
    const EVENT_EXPENSE = 'EXPENSE';

    // Event Sources
    const SOURCE_SALES = 'sales';
    const SOURCE_INVENTORY = 'inventory';
    const SOURCE_SYSTEM = 'system';
    const SOURCE_MANUAL = 'manual';

    /**
     * Account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Accounting Document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }

    /**
     * Disable updates - این جدول فقط INSERT است
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('Financial Ledger is IMMUTABLE. Updates are not allowed. Create a reversal entry instead.');
    }

    /**
     * Disable deletes - این جدول فقط INSERT است
     */
    public function delete()
    {
        throw new \Exception('Financial Ledger is IMMUTABLE. Deletes are not allowed. Create a reversal entry instead.');
    }

    /**
     * Scope: By account
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: By store
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Debit entries only
     */
    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope: Credit entries only
     */
    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Get net amount (debit - credit)
     */
    public function getNetAmount(): float
    {
        return $this->debit_amount - $this->credit_amount;
    }

    /**
     * Check if this is a debit entry
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if this is a credit entry
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }
}
