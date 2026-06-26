<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RMS\Core\Models\Setting;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'rate', 'tax_type', 'account_receivable_id',
        'account_payable_id', 'is_default', 'active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    const TYPE_VAT = 'vat';
    const TYPE_INCOME_TAX = 'income_tax';
    const TYPE_WITHHOLDING_TAX = 'withholding_tax';
    const TYPE_OTHER = 'other';

    protected static function booted(): void
    {
        static::observe(\RMS\Accounting\Observers\TaxRateObserver::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public static function getDefaultVAT(): ?self
    {
        return self::where('tax_type', self::TYPE_VAT)
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    public static function resolveEffectiveDefaultVAT(?string $forDate = null): ?self
    {
        $date = $forDate ?: now()->toDateString();

        return self::query()
            ->where('tax_type', self::TYPE_VAT)
            ->where('is_default', true)
            ->where('active', true)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public static function syncVatSettingFromDefault(?string $forDate = null): ?float
    {
        $defaultVat = self::resolveEffectiveDefaultVAT($forDate);
        if (! $defaultVat) {
            return null;
        }

        $rate = (float) $defaultVat->rate;
        Setting::set('accounting.vat.rate', $rate);

        return $rate;
    }
}
