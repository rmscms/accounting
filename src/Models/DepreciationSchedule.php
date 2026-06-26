<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_asset_id',
        'period_date',
        'opening_book_value',
        'depreciation_amount',
        'closing_book_value',
        'units_produced',
        'posted',
        'accounting_document_id',
    ];

    protected $casts = [
        'period_date' => 'date',
        'opening_book_value' => 'decimal:4',
        'depreciation_amount' => 'decimal:4',
        'closing_book_value' => 'decimal:4',
        'units_produced' => 'integer',
        'posted' => 'boolean',
    ];

    /**
     * دارایی ثابت
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * سند حسابداری
     */
    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    /**
     * Scope: Posted
     */
    public function scopePosted($query)
    {
        return $query->where('posted', true);
    }

    /**
     * Scope: Pending
     */
    public function scopePending($query)
    {
        return $query->where('posted', false);
    }
}
