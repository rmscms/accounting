<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\EmployeeContract;

class EmployeeContractService
{
    public function createContract(array $payload): EmployeeContract
    {
        return DB::transaction(function () use ($payload): EmployeeContract {
            $data = $this->normalizePayload($payload);
            $this->assertNoOverlap((int) $data['employee_id'], (string) $data['effective_from'], $data['effective_to']);

            if (($data['status'] ?? '') === EmployeeContract::STATUS_ACTIVE) {
                $this->closePreviousActiveContracts(
                    (int) $data['employee_id'],
                    (string) $data['effective_from']
                );
            }

            if (empty($data['contract_number'])) {
                $data['contract_number'] = EmployeeContract::generateContractNumber();
            }

            return EmployeeContract::query()->create($data);
        });
    }

    public function updateContract(EmployeeContract $contract, array $payload): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $payload): EmployeeContract {
            $data = $this->normalizePayload($payload);
            $this->assertNoOverlap(
                (int) $data['employee_id'],
                (string) $data['effective_from'],
                $data['effective_to'],
                (int) $contract->id
            );

            if (($data['status'] ?? '') === EmployeeContract::STATUS_ACTIVE) {
                $this->closePreviousActiveContracts(
                    (int) $data['employee_id'],
                    (string) $data['effective_from'],
                    (int) $contract->id
                );
            }

            $contract->update($data);

            return $contract->fresh();
        });
    }

    public function endContract(EmployeeContract $contract, string $endDate): EmployeeContract
    {
        $end = Carbon::parse($endDate)->format('Y-m-d');
        if ($end < (string) $contract->effective_from?->format('Y-m-d')) {
            throw new \InvalidArgumentException(trans('accounting::accounting.employee_contracts.errors.invalid_date_range'));
        }

        $contract->update([
            'effective_to' => $end,
            'status' => EmployeeContract::STATUS_ENDED,
        ]);

        return $contract->fresh();
    }

    public function cancelContract(EmployeeContract $contract): EmployeeContract
    {
        $contract->update(['status' => EmployeeContract::STATUS_CANCELLED]);

        return $contract->fresh();
    }

    /**
     * @return array<int,array<string,float|null>>
     */
    public function salaryDefaultsByEmployee(string $asOfDate): array
    {
        $targetDate = Carbon::parse($asOfDate)->format('Y-m-d');
        $defaults = [];

        $contracts = EmployeeContract::query()
            ->where('status', EmployeeContract::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $targetDate)
            ->where(static function ($query) use ($targetDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get([
                'employee_id',
                'base_salary',
                'seniority_monthly_default',
                'employee_insurance_rate',
                'employer_insurance_rate',
                'tax_rate',
            ]);

        foreach ($contracts as $contract) {
            $employeeId = (int) $contract->employee_id;
            if (isset($defaults[$employeeId])) {
                continue;
            }
            $defaults[$employeeId] = [
                'base_salary' => (float) $contract->base_salary,
                'seniority_monthly_default' => $contract->seniority_monthly_default !== null ? (float) $contract->seniority_monthly_default : null,
                'employee_insurance_rate' => $contract->employee_insurance_rate !== null ? (float) $contract->employee_insurance_rate : null,
                'employer_insurance_rate' => $contract->employer_insurance_rate !== null ? (float) $contract->employer_insurance_rate : null,
                'tax_rate' => $contract->tax_rate !== null ? (float) $contract->tax_rate : null,
            ];
        }

        return $defaults;
    }

    /**
     * @param int|null $ignoreId
     */
    protected function assertNoOverlap(int $employeeId, string $effectiveFrom, ?string $effectiveTo, ?int $ignoreId = null): void
    {
        $query = EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->where('status', '!=', EmployeeContract::STATUS_CANCELLED);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        /** @var EmployeeContract $existing */
        foreach ($query->get(['id', 'effective_from', 'effective_to']) as $existing) {
            if (EmployeeContract::rangesOverlap(
                $existing->effective_from?->format('Y-m-d') ?? '',
                $existing->effective_to?->format('Y-m-d'),
                $effectiveFrom,
                $effectiveTo
            )) {
                throw new \RuntimeException(trans('accounting::accounting.employee_contracts.errors.overlap'));
            }
        }
    }

    protected function closePreviousActiveContracts(int $employeeId, string $newEffectiveFrom, ?int $ignoreId = null): void
    {
        $dayBefore = Carbon::parse($newEffectiveFrom)->subDay()->format('Y-m-d');

        $query = EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->where('status', EmployeeContract::STATUS_ACTIVE);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        /** @var EmployeeContract $contract */
        foreach ($query->get() as $contract) {
            if ((string) $contract->effective_from?->format('Y-m-d') > $newEffectiveFrom) {
                continue;
            }
            $contract->update([
                'effective_to' => $dayBefore,
                'status' => EmployeeContract::STATUS_ENDED,
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        $effectiveFrom = Carbon::parse((string) $payload['effective_from'])->format('Y-m-d');
        $effectiveTo = ! empty($payload['effective_to']) ? Carbon::parse((string) $payload['effective_to'])->format('Y-m-d') : null;
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new \InvalidArgumentException(trans('accounting::accounting.employee_contracts.errors.invalid_date_range'));
        }

        return [
            'employee_id' => (int) ($payload['employee_id'] ?? 0),
            'contract_number' => (string) ($payload['contract_number'] ?? ''),
            'status' => (string) ($payload['status'] ?? EmployeeContract::STATUS_DRAFT),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'signed_at' => ! empty($payload['signed_at']) ? Carbon::parse((string) $payload['signed_at'])->format('Y-m-d') : null,
            'base_salary' => (float) ($payload['base_salary'] ?? 0),
            'seniority_monthly_default' => isset($payload['seniority_monthly_default']) && $payload['seniority_monthly_default'] !== ''
                ? (float) $payload['seniority_monthly_default']
                : null,
            'salary_cycle' => (string) ($payload['salary_cycle'] ?? EmployeeContract::SALARY_CYCLE_MONTHLY),
            'employee_insurance_rate' => isset($payload['employee_insurance_rate']) ? (float) $payload['employee_insurance_rate'] : null,
            'employer_insurance_rate' => isset($payload['employer_insurance_rate']) ? (float) $payload['employer_insurance_rate'] : null,
            'tax_rate' => isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : null,
            'notes' => (string) ($payload['notes'] ?? ''),
            'created_by_user_id' => $payload['created_by_user_id'] ?? null,
            'updated_by_user_id' => $payload['updated_by_user_id'] ?? null,
        ];
    }
}
