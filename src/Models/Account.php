<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'level',
        'parent_id',
        'account_type',
        'is_system',
        'currency_code',
        'active',
        'description',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_system' => 'boolean',
        'active' => 'boolean',
    ];

    // Account Types
    const TYPE_ASSET = 'asset';
    const TYPE_LIABILITY = 'liability';
    const TYPE_EQUITY = 'equity';
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    // Account Levels
    const LEVEL_GENERAL = 1;      // کل
    const LEVEL_SUBSIDIARY = 2;   // معین
    const LEVEL_ANALYTICAL = 3;   // تفصیلی

    /**
     * Parent Account
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Child Accounts
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Financial Ledger Entries
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedger::class, 'account_id');
    }

    /**
     * Get balance from ledger
     */
    public function getBalance(?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->ledgerEntries();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $debit = $query->sum('debit_amount');
        $credit = $query->sum('credit_amount');

        // برای دارایی و هزینه: بدهکار - بستانکار
        // برای بدهی، حقوق صاحبان سهام و درآمد: بستانکار - بدهکار
        if (in_array($this->account_type, [self::TYPE_ASSET, self::TYPE_EXPENSE])) {
            return $debit - $credit;
        }

        return $credit - $debit;
    }

    /**
     * Scope: Active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope: By level
     */
    public function scopeOfLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: System accounts
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Check if account is debit normal
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->account_type, [self::TYPE_ASSET, self::TYPE_EXPENSE]);
    }

    /**
     * Check if account is credit normal
     */
    public function isCreditNormal(): bool
    {
        return in_array($this->account_type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME]);
    }

    /**
     * Get full hierarchy path
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
