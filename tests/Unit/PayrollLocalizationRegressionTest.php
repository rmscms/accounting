<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PayrollLocalizationRegressionTest extends TestCase
{
    /** @test */
    public function payroll_insurance_keys_exist_in_farsi_and_english(): void
    {
        $fa = require __DIR__ . '/../../resources/lang/fa/accounting.php';
        $en = require __DIR__ . '/../../resources/lang/en/accounting.php';

        $required = [
            'page_title',
            'help_toggle',
            'help_p1',
            'help_p2',
            'amount_label',
            'journal_date_label',
            'bank_label',
            'bank_placeholder',
            'description_label',
            'submit_accrual',
            'submit_payment',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $fa['payroll_insurance']);
            $this->assertArrayHasKey($key, $en['payroll_insurance']);
        }
    }

    /** @test */
    public function payroll_run_keys_exist_for_new_module_labels(): void
    {
        $fa = require __DIR__ . '/../../resources/lang/fa/accounting.php';
        $en = require __DIR__ . '/../../resources/lang/en/accounting.php';

        $this->assertArrayHasKey('payroll_runs', $fa);
        $this->assertArrayHasKey('payroll_runs', $en);
        $this->assertArrayHasKey('statuses', $fa['payroll_runs']);
        $this->assertArrayHasKey('statuses', $en['payroll_runs']);
        $this->assertArrayHasKey('draft', $fa['payroll_runs']['statuses']);
        $this->assertArrayHasKey('closed', $en['payroll_runs']['statuses']);
        $this->assertArrayHasKey('policy', $fa['payroll_runs']);
        $this->assertArrayHasKey('items', $en['payroll_runs']);
        $this->assertArrayHasKey('print_payslip', $fa['payroll_runs']['actions']);
        $this->assertArrayHasKey('gross_salary', $en['payroll_runs']['fields']);
    }
}
