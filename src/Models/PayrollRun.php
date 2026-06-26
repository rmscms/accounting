<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACCRUED = 'accrued';
    public const STATUS_PAID = 'paid';
    public const STATUS_INSURANCE_REMITTED = 'insurance_remitted';
    public const STATUS_TAX_REMITTED = 'tax_remitted';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'run_number',
        'title',
        'period_start',
        'period_end',
        'journal_date',
        'currency_code',
        'status',
        'total_base_salary',
        'total_benefits',
        'total_seniority',
        'total_gross',
        'total_employee_insurance',
        'total_employer_insurance',
        'total_tax',
        'total_other_deductions',
        'total_net',
        'accrual_manual_journal_id',
        'net_payment_manual_journal_id',
        'insurance_remittance_manual_journal_id',
        'tax_remittance_manual_journal_id',
        'loan_settlement_manual_journal_id',
        'seniority_settlement_manual_journal_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'journal_date' => 'date',
        'total_base_salary' => 'decimal:4',
        'total_benefits' => 'decimal:4',
        'total_seniority' => 'decimal:4',
        'total_gross' => 'decimal:4',
        'total_employee_insurance' => 'decimal:4',
        'total_employer_insurance' => 'decimal:4',
        'total_tax' => 'decimal:4',
        'total_other_deductions' => 'decimal:4',
        'total_net' => 'decimal:4',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class)->orderBy('line_number');
    }

    public function lineItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            PayrollRunLineItem::class,
            PayrollRunLine::class,
            'payroll_run_id',
            'payroll_run_line_id'
        );
    }

    public function attendanceSnapshots(): HasMany
    {
        return $this->hasMany(PayrollAttendanceSnapshot::class, 'payroll_run_id');
    }

    public function accrualJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'accrual_manual_journal_id');
    }

    public function netPaymentJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'net_payment_manual_journal_id');
    }

    public function insuranceRemittanceJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'insurance_remittance_manual_journal_id');
    }

    public function taxRemittanceJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'tax_remittance_manual_journal_id');
    }

    public function loanSettlementJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'loan_settlement_manual_journal_id');
    }

    public function senioritySettlementJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'seniority_settlement_manual_journal_id');
    }

    public static function generateRunNumber(): string
    {
        $next = ((int) static::query()->max('id')) + 1;

        return 'PR-' . now()->format('Ymd') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
