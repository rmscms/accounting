<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePeriod extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_SUPERVISOR_APPROVED = 'supervisor_approved';
    public const STATUS_HR_APPROVED = 'hr_approved';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'period_start',
        'period_end',
        'period_key',
        'status',
        'policy_profile_id',
        'submitted_at',
        'supervisor_approved_at',
        'hr_approved_at',
        'locked_at',
        'lock_reason',
        'unlock_reason',
        'submitted_by_user_id',
        'supervisor_approved_by_user_id',
        'hr_approved_by_user_id',
        'locked_by_user_id',
        'unlocked_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'supervisor_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function policyProfile(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicyProfile::class, 'policy_profile_id');
    }

    public function dailyRows(): HasMany
    {
        return $this->hasMany(AttendanceDaily::class, 'attendance_period_id');
    }
}
