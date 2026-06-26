<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRunLine extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'line_number',
        'base_salary',
        'benefits',
        'seniority',
        'gross_salary',
        'employee_insurance',
        'employer_insurance',
        'tax',
        'other_deductions',
        'skip_loan_deduction',
        'net_salary',
        'description',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'base_salary' => 'decimal:4',
        'benefits' => 'decimal:4',
        'seniority' => 'decimal:4',
        'gross_salary' => 'decimal:4',
        'employee_insurance' => 'decimal:4',
        'employer_insurance' => 'decimal:4',
        'tax' => 'decimal:4',
        'other_deductions' => 'decimal:4',
        'skip_loan_deduction' => 'boolean',
        'net_salary' => 'decimal:4',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunLineItem::class)->orderBy('sort_order');
    }

    public function attendanceSnapshot(): HasOne
    {
        return $this->hasOne(PayrollAttendanceSnapshot::class, 'payroll_run_line_id');
    }
}
