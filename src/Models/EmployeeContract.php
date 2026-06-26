<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeContract extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    public const SALARY_CYCLE_MONTHLY = 'monthly';

    protected $fillable = [
        'employee_id',
        'contract_number',
        'status',
        'effective_from',
        'effective_to',
        'signed_at',
        'base_salary',
        'seniority_monthly_default',
        'salary_cycle',
        'employee_insurance_rate',
        'employer_insurance_rate',
        'tax_rate',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'signed_at' => 'date',
        'base_salary' => 'decimal:4',
        'seniority_monthly_default' => 'decimal:4',
        'employee_insurance_rate' => 'decimal:4',
        'employer_insurance_rate' => 'decimal:4',
        'tax_rate' => 'decimal:4',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_ENDED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function generateContractNumber(): string
    {
        $next = ((int) static::query()->max('id')) + 1;

        return 'EC-' . now()->format('Ymd') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public static function rangesOverlap(
        Carbon|string $leftStart,
        Carbon|string|null $leftEnd,
        Carbon|string $rightStart,
        Carbon|string|null $rightEnd
    ): bool {
        $leftStartDate = $leftStart instanceof Carbon ? $leftStart->copy()->startOfDay() : Carbon::parse((string) $leftStart)->startOfDay();
        $rightStartDate = $rightStart instanceof Carbon ? $rightStart->copy()->startOfDay() : Carbon::parse((string) $rightStart)->startOfDay();

        $leftEndDate = $leftEnd instanceof Carbon
            ? $leftEnd->copy()->endOfDay()
            : ($leftEnd ? Carbon::parse((string) $leftEnd)->endOfDay() : null);
        $rightEndDate = $rightEnd instanceof Carbon
            ? $rightEnd->copy()->endOfDay()
            : ($rightEnd ? Carbon::parse((string) $rightEnd)->endOfDay() : null);

        $leftBound = $leftEndDate?->timestamp ?? PHP_INT_MAX;
        $rightBound = $rightEndDate?->timestamp ?? PHP_INT_MAX;

        return $leftStartDate->timestamp <= $rightBound && $rightStartDate->timestamp <= $leftBound;
    }
}
