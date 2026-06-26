<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_schedule_id',
        'entry_date',
        'depreciation_amount',
        'accounting_document_id',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'depreciation_amount' => 'decimal:4',
    ];

    /**
     * دارایی ثابت
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * برنامه استهلاک
     */
    public function depreciationSchedule(): BelongsTo
    {
        return $this->belongsTo(DepreciationSchedule::class);
    }

    /**
     * سند حسابداری
     */
    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }
}
