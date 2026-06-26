<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoanInstallment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_loan_id',
        'installment_number',
        'due_date',
        'opening_principal',
        'principal_amount',
        'interest_amount',
        'installment_amount',
        'paid_principal',
        'paid_interest',
        'paid_total',
        'remaining_amount',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'opening_principal' => 'decimal:4',
        'principal_amount' => 'decimal:4',
        'interest_amount' => 'decimal:4',
        'installment_amount' => 'decimal:4',
        'paid_principal' => 'decimal:4',
        'paid_interest' => 'decimal:4',
        'paid_total' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmployeeLoanPayment::class, 'employee_loan_installment_id');
    }
}
