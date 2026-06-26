<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shareholder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'national_id',
        'email',
        'phone',
        'notes',
        'capital_account_id',
        'drawings_account_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'capital_account_id');
    }

    public function drawingsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'drawings_account_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(ShareholderWithdrawal::class);
    }

    public function capitalContributions(): HasMany
    {
        return $this->hasMany(ShareholderCapitalContribution::class);
    }
}
