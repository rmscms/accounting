<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Services\EmployeeLoanService;
use RMS\Accounting\Services\ManualJournalService;
use RMS\Accounting\Services\SystemAccountLocator;

class EmployeeLoanServiceTest extends TestCase
{
    /** @test */
    public function it_generates_installment_schedule_with_principal_and_interest_breakdown(): void
    {
        $service = new EmployeeLoanService(
            $this->createMock(ManualJournalService::class),
            $this->createMock(SystemAccountLocator::class)
        );

        $schedule = $service->buildInstallmentSchedule(12000000, 12, 12, '2026-01-31');

        $this->assertCount(12, $schedule);
        $this->assertSame('2026-01-31', $schedule[0]['due_date']);
        $this->assertSame('2026-12-31', $schedule[11]['due_date']);
        $this->assertGreaterThan(0, $schedule[0]['interest_amount']);
        $this->assertGreaterThan(0, $schedule[0]['principal_amount']);
        $this->assertEquals(12000000.0, round(array_sum(array_column($schedule, 'principal_amount')), 0));
    }
}
