<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'branch_name',
        'account_number',
        'iban',
        'swift_code',
        'account_id',
        'balance',
        'currency_code',
        'active',
        'notes',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Bank $bank): void {
            $bank->currency_code = static::resolveCurrencyCodeForSave($bank->currency_code);
        });
    }

    protected static function resolveCurrencyCodeForSave(mixed $rawCode): string
    {
        $normalized = strtoupper(trim((string) $rawCode));
        if ($normalized !== '') {
            return $normalized;
        }

        return Currency::resolveBaseCurrencyCode('IRR');
    }

    /**
     * Linked Account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Currency
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * عنوان کوتاه برای کشوها و گزارش‌ها؛ در صورت خالی بودن از نام بانک استفاده می‌شود.
     */
    public function getLabelForSelectAttribute(): string
    {
        $s = trim((string) ($this->short_name ?? ''));

        return $s !== '' ? $s : (string) ($this->name ?? '');
    }

    /**
     * POS Terminals
     */
    public function posTerminals(): HasMany
    {
        return $this->hasMany(POSTerminal::class);
    }

    /**
     * Cheques
     */
    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function chequebooks(): HasMany
    {
        return $this->hasMany(Chequebook::class);
    }

    /**
     * Scope: Active
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
