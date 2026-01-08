<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
