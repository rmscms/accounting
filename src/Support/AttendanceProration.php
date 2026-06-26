<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

final class AttendanceProration
{
    /**
     * @return array{factor:float,base_salary:float,benefits:float,insurable_base:float,taxable_base:float}
     */
    public static function prorate(float $baseSalary, float $benefits, float $payableDays, float $standardMonthDays): array
    {
        if ($standardMonthDays <= 0.0) {
            return [
                'factor' => 1.0,
                'base_salary' => round(max(0.0, $baseSalary), 4),
                'benefits' => round(max(0.0, $benefits), 4),
                'insurable_base' => round(max(0.0, $baseSalary + $benefits), 4),
                'taxable_base' => round(max(0.0, $baseSalary + $benefits), 4),
            ];
        }

        $factor = round(min(1.0, max(0.0, $payableDays / $standardMonthDays)), 6);
        $proratedBase = round(max(0.0, $baseSalary) * $factor, 4);
        $proratedBenefits = round(max(0.0, $benefits) * $factor, 4);
        $base = round($proratedBase + $proratedBenefits, 4);

        return [
            'factor' => $factor,
            'base_salary' => $proratedBase,
            'benefits' => $proratedBenefits,
            'insurable_base' => $base,
            'taxable_base' => $base,
        ];
    }
}
