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
        'decimals',
        'is_base',
        'is_reference',
        'active',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'is_base' => 'boolean',
        'is_reference' => 'boolean',
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

    public static function resolveBaseCurrencyCode(string $fallback = 'IRR'): string
    {
        $base = self::query()
            ->where('is_base', true)
            ->where('active', true)
            ->value('code');
        if (is_string($base) && trim($base) !== '') {
            return strtoupper(trim($base));
        }

        $baseAny = self::query()->where('is_base', true)->value('code');
        if (is_string($baseAny) && trim($baseAny) !== '') {
            return strtoupper(trim($baseAny));
        }

        $active = self::query()->active()->orderBy('code')->value('code');
        if (is_string($active) && trim($active) !== '') {
            return strtoupper(trim($active));
        }

        return strtoupper(trim($fallback));
    }
}
