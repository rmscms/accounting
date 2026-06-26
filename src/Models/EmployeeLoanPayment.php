<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanPayment extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'employee_loan_installment_id',
        'payroll_run_id',
        'payment_date',
        'source',
        'principal_amount',
        'interest_amount',
        'amount',
        'manual_journal_id',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'principal_amount' => 'decimal:4',
        'interest_amount' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoanInstallment::class, 'employee_loan_installment_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'manual_journal_id');
    }
}
