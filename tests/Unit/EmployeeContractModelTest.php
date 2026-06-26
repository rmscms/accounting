<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Models\EmployeeContract;

class EmployeeContractModelTest extends TestCase
{
    /** @test */
    public function it_detects_overlapping_ranges_with_open_end_dates(): void
    {
        $this->assertTrue(
            EmployeeContract::rangesOverlap('2026-01-01', null, '2026-05-01', '2026-10-01')
        );

        $this->assertFalse(
            EmployeeContract::rangesOverlap('2026-01-01', '2026-03-31', '2026-04-01', '2026-12-31')
        );
    }
}
