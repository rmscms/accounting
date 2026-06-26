<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'national_id',
        'email',
        'phone',
        'notes',
        'payroll_expense_account_id',
        'wages_payable_account_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function payrollExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payroll_expense_account_id');
    }

    public function wagesPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'wages_payable_account_id');
    }

    public function payrollRunLines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class)->orderByDesc('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function latestActiveContractAsOf(string|Carbon|null $asOfDate = null): ?EmployeeContract
    {
        $targetDate = $asOfDate instanceof Carbon
            ? $asOfDate->copy()->format('Y-m-d')
            : ($asOfDate ? Carbon::parse($asOfDate)->format('Y-m-d') : now()->format('Y-m-d'));

        return $this->contracts()
            ->where('status', EmployeeContract::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $targetDate)
            ->where(static function ($query) use ($targetDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
