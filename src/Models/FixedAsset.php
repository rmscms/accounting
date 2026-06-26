<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_code',
        'name',
        'category_id',
        'purchase_date',
        'purchase_price',
        'useful_life_years',
        'useful_life_months',
        'depreciation_method',
        'declining_balance_rate',
        'total_units',
        'salvage_value',
        'accumulated_depreciation',
        'book_value',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'status',
        'disposal_date',
        'disposal_value',
        'location',
        'serial_number',
        'description',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'disposal_date' => 'date',
        'purchase_price' => 'decimal:4',
        'salvage_value' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'book_value' => 'decimal:4',
        'disposal_value' => 'decimal:4',
        'declining_balance_rate' => 'decimal:2',
        'useful_life_years' => 'integer',
        'useful_life_months' => 'integer',
        'total_units' => 'integer',
    ];

    /**
     * دسته‌بندی
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    /**
     * حساب دارایی
     */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /**
     * حساب هزینه استهلاک
     */
    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    /**
     * حساب استهلاک انباشته
     */
    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    /**
     * برنامه استهلاک
     */
    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(DepreciationSchedule::class);
    }

    /**
     * ثبت‌های استهلاک
     */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Disposed
     */
    public function scopeDisposed($query)
    {
        return $query->where('status', 'disposed');
    }

    /**
     * Generate unique asset code
     */
    public static function generateAssetCode(): string
    {
        $lastAsset = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastAsset ? ($lastAsset->id + 1) : 1;
        return 'FA-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
