<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_base',
        'active',
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Currency Rates
     */
    public function rates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class, 'currency_code', 'code');
    }

    /**
     * Get latest rate to IRR
     */
    public function getLatestRate(): ?float
    {
        $rate = $this->rates()->latest('rate_date')->first();
        return $rate ? $rate->rate_to_irr : null;
    }

    /**
     * Get rate for specific date
     */
    public function getRateForDate(string $date): ?float
    {
        $rate = $this->rates()
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();
            
        return $rate ? $rate->rate_to_irr : $this->getLatestRate();
    }

    /**
     * Scope: Active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get base currency (IRR)
     */
    public static function getBaseCurrency(): ?self
    {
        return self::where('is_base', true)->first();
    }
}
