<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAssetCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

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
     * دارایی‌های این دسته
     */
    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
