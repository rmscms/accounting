<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAttendanceSnapshot extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'payroll_run_line_id',
        'employee_id',
        'attendance_period_id',
        'policy_profile_id',
        'planned_days',
        'worked_days',
        'payable_days',
        'proration_factor',
        'prorated_base_salary',
        'prorated_benefits',
        'prorated_insurable_base',
        'prorated_taxable_base',
        'source_breakdown_json',
    ];

    protected $casts = [
        'planned_days' => 'decimal:4',
        'worked_days' => 'decimal:4',
        'payable_days' => 'decimal:4',
        'proration_factor' => 'decimal:6',
        'prorated_base_salary' => 'decimal:4',
        'prorated_benefits' => 'decimal:4',
        'prorated_insurable_base' => 'decimal:4',
        'prorated_taxable_base' => 'decimal:4',
        'source_breakdown_json' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class, 'payroll_run_line_id');
    }
}
