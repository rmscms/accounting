<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Services\AccountingDateInputNormalizer;

class AccountingDateInputNormalizerTest extends TestCase
{
    /** @test */
    public function it_passes_through_iso_gregorian_dates(): void
    {
        $n = new AccountingDateInputNormalizer();

        $this->assertSame('2024-06-01', $n->normalizeFilterDateToGregorian('2024-06-01'));
        $this->assertSame('1999-12-31', $n->normalizeFilterDateToGregorian('1999-12-31'));
    }

    /** @test */
    public function it_returns_null_for_empty_or_whitespace(): void
    {
        $n = new AccountingDateInputNormalizer();

        $this->assertNull($n->normalizeFilterDateToGregorian(null));
        $this->assertNull($n->normalizeFilterDateToGregorian(''));
        $this->assertNull($n->normalizeFilterDateToGregorian('   '));
    }

    /** @test */
    public function parse_flexible_date_mirrors_iso_handling(): void
    {
        $n = new AccountingDateInputNormalizer();

        $this->assertSame('2020-01-15', $n->parseFlexibleDateFilter('2020-01-15'));
        $this->assertNull($n->parseFlexibleDateFilter(''));
    }
}
