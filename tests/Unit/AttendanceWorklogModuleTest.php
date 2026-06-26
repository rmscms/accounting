<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Support\AttendanceProration;

class AttendanceWorklogModuleTest extends TestCase
{
    /** @test */
    public function attendance_translations_exist_in_both_locales(): void
    {
        $fa = require __DIR__ . '/../../resources/lang/fa/accounting.php';
        $en = require __DIR__ . '/../../resources/lang/en/accounting.php';

        $this->assertArrayHasKey('attendance', $fa);
        $this->assertArrayHasKey('attendance', $en);
        $this->assertArrayHasKey('actions', $fa['attendance']);
        $this->assertArrayHasKey('actions', $en['attendance']);
        $this->assertArrayHasKey('errors', $fa['attendance']);
        $this->assertArrayHasKey('errors', $en['attendance']);
        $this->assertArrayHasKey('payroll_proration_hint', $fa['attendance']);
        $this->assertArrayHasKey('payroll_proration_hint', $en['attendance']);
        $this->assertArrayHasKey('period_requires_hr_approval', $fa['attendance']['errors']);
        $this->assertArrayHasKey('period_requires_hr_approval', $en['attendance']['errors']);
    }

    /** @test */
    public function it_prorates_salary_for_mid_month_termination_scenario(): void
    {
        $result = AttendanceProration::prorate(30000000, 6000000, 15, 30);

        $this->assertSame(0.5, $result['factor']);
        $this->assertSame(15000000.0, $result['base_salary']);
        $this->assertSame(3000000.0, $result['benefits']);
        $this->assertSame(18000000.0, $result['insurable_base']);
        $this->assertSame(18000000.0, $result['taxable_base']);
    }

    /** @test */
    public function it_caps_proration_factor_at_one_for_full_month_or_more(): void
    {
        $result = AttendanceProration::prorate(12000000, 2000000, 33, 30);

        $this->assertSame(1.0, $result['factor']);
        $this->assertSame(12000000.0, $result['base_salary']);
        $this->assertSame(2000000.0, $result['benefits']);
    }
}
<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Support\AttendanceProration;

class AttendanceWorklogModuleTest extends TestCase
{
    /** @test */
    public function attendance_translations_exist_in_both_locales(): void
    {
        $fa = require __DIR__ . '/../../resources/lang/fa/accounting.php';
        $en = require __DIR__ . '/../../resources/lang/en/accounting.php';

        $this->assertArrayHasKey('attendance', $fa);
        $this->assertArrayHasKey('attendance', $en);
        $this->assertArrayHasKey('actions', $fa['attendance']);
        $this->assertArrayHasKey('actions', $en['attendance']);
        $this->assertArrayHasKey('errors', $fa['attendance']);
        $this->assertArrayHasKey('errors', $en['attendance']);
        $this->assertArrayHasKey('payroll_proration_hint', $fa['attendance']);
        $this->assertArrayHasKey('payroll_proration_hint', $en['attendance']);
        $this->assertArrayHasKey('period_requires_hr_approval', $fa['attendance']['errors']);
        $this->assertArrayHasKey('period_requires_hr_approval', $en['attendance']['errors']);
    }

    /** @test */
    public function it_prorates_salary_for_mid_month_termination_scenario(): void
    {
        $result = AttendanceProration::prorate(30000000, 6000000, 15, 30);

        $this->assertSame(0.5, $result['factor']);
        $this->assertSame(15000000.0, $result['base_salary']);
        $this->assertSame(3000000.0, $result['benefits']);
        $this->assertSame(18000000.0, $result['insurable_base']);
        $this->assertSame(18000000.0, $result['taxable_base']);
    }

    /** @test */
    public function it_caps_proration_factor_at_one_for_full_month_or_more(): void
    {
        $result = AttendanceProration::prorate(12000000, 2000000, 33, 30);

        $this->assertSame(1.0, $result['factor']);
        $this->assertSame(12000000.0, $result['base_salary']);
        $this->assertSame(2000000.0, $result['benefits']);
    }
}
