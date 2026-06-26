<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

final class PayrollCalculator
{
    public const DEFAULT_EMPLOYEE_INSURANCE_RATE = 0.07;
    public const DEFAULT_EMPLOYER_INSURANCE_RATE = 0.23;
    public const DEFAULT_TAX_RATE = 0.0;

    /**
     * @param  array<string,mixed>  $line
     * @param  array<string,mixed>  $policy
     * @return array<string,float>
     */
    public static function computeLine(array $line, array $policy = []): array
    {
        $base = self::toFloat($line['base_salary'] ?? 0);
        $benefits = self::toFloat($line['benefits'] ?? 0);
        $seniority = self::toFloat($line['seniority'] ?? 0);
        $insurableTaxableBase = $base + $benefits;
        $gross = self::toFloat($line['gross_salary'] ?? $insurableTaxableBase);
        $otherDeductions = self::toFloat($line['other_deductions'] ?? 0);

        $employeeRate = self::normalizeRate($policy['employee_insurance_rate'] ?? self::DEFAULT_EMPLOYEE_INSURANCE_RATE);
        $employerRate = self::normalizeRate($policy['employer_insurance_rate'] ?? self::DEFAULT_EMPLOYER_INSURANCE_RATE);
        $taxRate = self::normalizeRate($policy['tax_rate'] ?? self::DEFAULT_TAX_RATE);

        $employeeInsurance = self::resolveSemiAutoAmount(
            $insurableTaxableBase,
            $employeeRate,
            $line,
            'employee_insurance'
        );
        $employerInsurance = self::resolveSemiAutoAmount(
            $insurableTaxableBase,
            $employerRate,
            $line,
            'employer_insurance'
        );
        $tax = self::resolveSemiAutoAmount(
            $insurableTaxableBase,
            $taxRate,
            $line,
            'tax'
        );
        $net = self::toFloat($line['net_salary'] ?? ($gross - $employeeInsurance - $tax - $otherDeductions));

        return [
            'base_salary' => round($base, 4),
            'benefits' => round($benefits, 4),
            'seniority' => round($seniority, 4),
            'gross_salary' => round($gross, 4),
            'employee_insurance' => round($employeeInsurance, 4),
            'employer_insurance' => round($employerInsurance, 4),
            'tax' => round($tax, 4),
            'other_deductions' => round($otherDeductions, 4),
            'net_salary' => round($net, 4),
        ];
    }

    /**
     * @param  array<string,mixed>  $line
     * @param  array<string,float>  $computed
     * @return array<int,array<string,mixed>>
     */
    public static function buildLineItems(array $line, array $computed): array
    {
        $items = [
            self::systemItem(1, 'earning', 'base_salary', 'base_salary', $computed['base_salary'], true, false),
            self::systemItem(2, 'earning', 'benefits', 'benefits', $computed['benefits'], true, false),
            self::systemItem(3, 'earning', 'seniority', 'seniority', $computed['seniority'] ?? 0.0, true, false),
            self::systemItem(
                4,
                'deduction',
                'employee_insurance',
                'employee_insurance',
                $computed['employee_insurance'],
                true,
                self::isManualEnabled($line, 'employee_insurance')
            ),
            self::systemItem(
                5,
                'employer_contribution',
                'employer_insurance',
                'employer_insurance',
                $computed['employer_insurance'],
                true,
                self::isManualEnabled($line, 'employer_insurance')
            ),
            self::systemItem(
                6,
                'deduction',
                'tax',
                'tax',
                $computed['tax'],
                true,
                self::isManualEnabled($line, 'tax')
            ),
            self::systemItem(7, 'deduction', 'other_deductions', 'other_deductions', $computed['other_deductions'], true, false),
        ];

        $custom = self::normalizeCustomItems((array) ($line['items'] ?? []), 100);

        return array_merge($items, $custom);
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array<string,float>
     */
    public static function recomputeLineFromItems(array $items): array
    {
        $baseSalary = 0.0;
        $benefits = 0.0;
        $seniority = 0.0;
        $employeeInsurance = 0.0;
        $employerInsurance = 0.0;
        $tax = 0.0;
        $otherDeductions = 0.0;
        $gross = 0.0;
        $totalDeductions = 0.0;

        foreach ($items as $item) {
            $amount = self::toFloat($item['amount'] ?? 0);
            $type = (string) ($item['type'] ?? '');
            $code = (string) ($item['code'] ?? '');

            if ($type === 'earning') {
                if ($code === 'base_salary') {
                    $gross += $amount;
                    $baseSalary += $amount;
                } elseif ($code === 'seniority') {
                    $seniority += $amount;
                } else {
                    $gross += $amount;
                    $benefits += $amount;
                }
            } elseif ($type === 'deduction') {
                $totalDeductions += $amount;
                if ($code === 'employee_insurance') {
                    $employeeInsurance += $amount;
                } elseif ($code === 'tax') {
                    $tax += $amount;
                } else {
                    $otherDeductions += $amount;
                }
            } elseif ($type === 'employer_contribution') {
                $employerInsurance += $amount;
            }
        }

        return [
            'base_salary' => round($baseSalary, 4),
            'benefits' => round($benefits, 4),
            'seniority' => round($seniority, 4),
            'gross_salary' => round($gross, 4),
            'employee_insurance' => round($employeeInsurance, 4),
            'employer_insurance' => round($employerInsurance, 4),
            'tax' => round($tax, 4),
            'other_deductions' => round($otherDeductions, 4),
            'net_salary' => round($gross - $totalDeductions, 4),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<string,float>
     */
    public static function summarize(array $lines): array
    {
        $summary = [
            'total_base_salary' => 0.0,
            'total_benefits' => 0.0,
            'total_seniority' => 0.0,
            'total_gross' => 0.0,
            'total_employee_insurance' => 0.0,
            'total_employer_insurance' => 0.0,
            'total_tax' => 0.0,
            'total_other_deductions' => 0.0,
            'total_net' => 0.0,
        ];

        foreach ($lines as $line) {
            $computed = self::computeLine($line);
            $summary['total_base_salary'] += $computed['base_salary'];
            $summary['total_benefits'] += $computed['benefits'];
            $summary['total_seniority'] += $computed['seniority'] ?? 0.0;
            $summary['total_gross'] += $computed['gross_salary'];
            $summary['total_employee_insurance'] += $computed['employee_insurance'];
            $summary['total_employer_insurance'] += $computed['employer_insurance'];
            $summary['total_tax'] += $computed['tax'];
            $summary['total_other_deductions'] += $computed['other_deductions'];
            $summary['total_net'] += $computed['net_salary'];
        }

        return $summary;
    }

    /**
     * @param  array<string,mixed>  $line
     */
    protected static function resolveSemiAutoAmount(float $gross, float $rate, array $line, string $field): float
    {
        if (self::isManualEnabled($line, $field) || array_key_exists($field, $line)) {
            return self::toFloat($line[$field] ?? 0);
        }

        return round($gross * $rate, 4);
    }

    /**
     * @param  array<string,mixed>  $line
     */
    protected static function isManualEnabled(array $line, string $field): bool
    {
        $flag = $field . '_manual';
        if (! array_key_exists($flag, $line)) {
            return false;
        }

        return filter_var($line[$flag], FILTER_VALIDATE_BOOLEAN);
    }

    protected static function normalizeRate(mixed $rate): float
    {
        $value = self::toFloat($rate);
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return round($value / 100, 6);
        }

        return round($value, 6);
    }

    protected static function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = (string) $value;
        $normalized = str_replace([',', '٬', '،', ' '], '', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * @return array<string,mixed>
     */
    protected static function systemItem(
        int $sortOrder,
        string $type,
        string $code,
        string $titleKey,
        float $amount,
        bool $isAutoCalculated,
        bool $isManualOverride
    ): array {
        return [
            'type' => $type,
            'code' => $code,
            'title' => (string) trans('accounting::accounting.payroll_runs.items.' . $titleKey),
            'amount' => round($amount, 4),
            'sort_order' => $sortOrder,
            'is_system' => true,
            'is_auto_calculated' => $isAutoCalculated,
            'is_manual_override' => $isManualOverride,
            'notes' => null,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    protected static function normalizeCustomItems(array $items, int $startSort): array
    {
        $result = [];
        $index = 0;
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if (! in_array($type, ['earning', 'deduction', 'employer_contribution'], true)) {
                continue;
            }

            $amount = self::toFloat($item['amount'] ?? 0);
            $code = trim((string) ($item['code'] ?? 'custom_' . $index));
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $result[] = [
                'type' => $type,
                'code' => $code !== '' ? $code : 'custom_' . $index,
                'title' => $title,
                'amount' => round($amount, 4),
                'sort_order' => $startSort + $index,
                'is_system' => false,
                'is_auto_calculated' => false,
                'is_manual_override' => true,
                'notes' => (string) ($item['notes'] ?? ''),
            ];
            $index++;
        }

        return $result;
    }
}
