<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePolicyProfile extends Model
{
    protected $fillable = [
        'title',
        'code',
        'is_default',
        'standard_month_days',
        'daily_work_minutes',
        'overtime_rate_multiplier',
        'taxable_allowance',
        'policy_json',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'standard_month_days' => 'integer',
        'daily_work_minutes' => 'integer',
        'overtime_rate_multiplier' => 'decimal:4',
        'taxable_allowance' => 'decimal:4',
        'policy_json' => 'array',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AttendancePeriod::class, 'policy_profile_id');
    }
}
