<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Support\PayrollCalculator;

class PayrollCalculatorTest extends TestCase
{
    /** @test */
    public function it_computes_scenario_46_single_employee_totals(): void
    {
        $line = PayrollCalculator::computeLine([
            'base_salary' => 15000000,
            'benefits' => 3000000,
            'employee_insurance' => 1260000,
            'employer_insurance' => 5400000,
            'tax' => 1000000,
            'other_deductions' => 0,
        ]);

        $this->assertSame(18000000.0, $line['gross_salary']);
        $this->assertSame(15740000.0, $line['net_salary']);
    }

    /** @test */
    public function it_summarizes_multiple_lines_for_journal_posting(): void
    {
        $summary = PayrollCalculator::summarize([
            [
                'base_salary' => 1000,
                'benefits' => 200,
                'employee_insurance' => 100,
                'employer_insurance' => 150,
                'tax' => 50,
                'other_deductions' => 10,
            ],
            [
                'base_salary' => 900,
                'benefits' => 100,
                'employee_insurance' => 80,
                'employer_insurance' => 120,
                'tax' => 40,
                'other_deductions' => 5,
            ],
        ]);

        $this->assertSame(1900.0, $summary['total_base_salary']);
        $this->assertSame(300.0, $summary['total_benefits']);
        $this->assertSame(2200.0, $summary['total_gross']);
        $this->assertSame(180.0, $summary['total_employee_insurance']);
        $this->assertSame(270.0, $summary['total_employer_insurance']);
        $this->assertSame(90.0, $summary['total_tax']);
        $this->assertSame(15.0, $summary['total_other_deductions']);
        $this->assertSame(1915.0, $summary['total_net']);
    }

    /** @test */
    public function it_supports_semi_auto_rates_with_manual_override_flags(): void
    {
        $line = PayrollCalculator::computeLine(
            [
                'base_salary' => 10000000,
                'benefits' => 2000000,
                'employee_insurance_manual' => false,
                'employer_insurance_manual' => true,
                'employer_insurance' => 3000000,
                'tax_manual' => false,
                'other_deductions' => 500000,
            ],
            [
                'employee_insurance_rate' => 7,
                'employer_insurance_rate' => 23,
                'tax_rate' => 10,
            ]
        );

        $this->assertSame(12000000.0, $line['gross_salary']);
        $this->assertSame(840000.0, $line['employee_insurance']);
        $this->assertSame(3000000.0, $line['employer_insurance']);
        $this->assertSame(1200000.0, $line['tax']);
        $this->assertSame(9460000.0, $line['net_salary']);
    }

    /** @test */
    public function it_can_recompute_line_from_detailed_items(): void
    {
        $totals = PayrollCalculator::recomputeLineFromItems([
            ['type' => 'earning', 'code' => 'base_salary', 'amount' => 1200],
            ['type' => 'earning', 'code' => 'bonus', 'amount' => 300],
            ['type' => 'deduction', 'code' => 'employee_insurance', 'amount' => 100],
            ['type' => 'deduction', 'code' => 'tax', 'amount' => 50],
            ['type' => 'deduction', 'code' => 'loan', 'amount' => 20],
            ['type' => 'employer_contribution', 'code' => 'employer_insurance', 'amount' => 150],
        ]);

        $this->assertSame(1200.0, $totals['base_salary']);
        $this->assertSame(300.0, $totals['benefits']);
        $this->assertSame(1500.0, $totals['gross_salary']);
        $this->assertSame(100.0, $totals['employee_insurance']);
        $this->assertSame(150.0, $totals['employer_insurance']);
        $this->assertSame(50.0, $totals['tax']);
        $this->assertSame(20.0, $totals['other_deductions']);
        $this->assertSame(1330.0, $totals['net_salary']);
    }

    /** @test */
    public function it_counts_loan_principal_and_interest_as_other_deductions(): void
    {
        $totals = PayrollCalculator::recomputeLineFromItems([
            ['type' => 'earning', 'code' => 'base_salary', 'amount' => 10000000],
            ['type' => 'deduction', 'code' => 'loan_principal', 'amount' => 2000000],
            ['type' => 'deduction', 'code' => 'loan_interest', 'amount' => 300000],
        ]);

        $this->assertSame(2300000.0, $totals['other_deductions']);
        $this->assertSame(7700000.0, $totals['net_salary']);
    }
}
