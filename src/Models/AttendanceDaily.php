<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDaily extends Model
{
    protected $table = 'attendance_daily';

    protected $fillable = [
        'attendance_period_id',
        'employee_id',
        'policy_profile_id',
        'work_date',
        'planned_minutes',
        'worked_minutes',
        'overtime_minutes',
        'late_minutes',
        'undertime_minutes',
        'leave_minutes',
        'absence_minutes',
        'worked_day_fraction',
        'payable_day_fraction',
        'status',
        'is_manual_override',
        'is_termination_final_day',
        'submitted_at',
        'supervisor_approved_at',
        'hr_approved_at',
        'locked_at',
        'submitted_by_user_id',
        'supervisor_approved_by_user_id',
        'hr_approved_by_user_id',
        'locked_by_user_id',
        'anomaly_flags_json',
        'meta_json',
        'notes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'planned_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'late_minutes' => 'integer',
        'undertime_minutes' => 'integer',
        'leave_minutes' => 'integer',
        'absence_minutes' => 'integer',
        'worked_day_fraction' => 'decimal:4',
        'payable_day_fraction' => 'decimal:4',
        'is_manual_override' => 'boolean',
        'is_termination_final_day' => 'boolean',
        'submitted_at' => 'datetime',
        'supervisor_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'anomaly_flags_json' => 'array',
        'meta_json' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policyProfile(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicyProfile::class, 'policy_profile_id');
    }
}
