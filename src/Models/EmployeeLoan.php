<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoan extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'loan_number',
        'employee_id',
        'disbursement_bank_id',
        'disbursement_date',
        'first_due_date',
        'principal_amount',
        'annual_interest_rate',
        'installments_count',
        'installment_amount',
        'total_interest_amount',
        'total_amount',
        'remaining_principal',
        'remaining_interest',
        'remaining_total',
        'status',
        'disbursement_manual_journal_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'disbursement_date' => 'date',
        'first_due_date' => 'date',
        'principal_amount' => 'decimal:4',
        'annual_interest_rate' => 'decimal:4',
        'installment_amount' => 'decimal:4',
        'total_interest_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'remaining_principal' => 'decimal:4',
        'remaining_interest' => 'decimal:4',
        'remaining_total' => 'decimal:4',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function disbursementBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'disbursement_bank_id');
    }

    public function disbursementJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'disbursement_manual_journal_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(EmployeeLoanInstallment::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmployeeLoanPayment::class)->orderBy('payment_date');
    }

    public static function generateLoanNumber(): string
    {
        $next = ((int) static::query()->max('id')) + 1;

        return 'EL-' . now()->format('Ymd') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
