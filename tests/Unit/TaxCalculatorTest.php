<?php

namespace RMS\Accounting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMS\Accounting\Services\Tax\TaxCalculator;

/**
 * تست‌های واحد برای TaxCalculator
 */
class TaxCalculatorTest extends TestCase
{
    /** @test */
    public function it_calculates_vat_exclusive_correctly()
    {
        $result = TaxCalculator::calculateVAT(100000, 9, 'exclusive');
        
        $this->assertEquals(9000, $result['tax_amount']);
        $this->assertEquals(109000, $result['total_amount']);
        $this->assertEquals(100000, $result['base_amount']);
        $this->assertEquals(9, $result['tax_rate']);
        $this->assertEquals('exclusive', $result['method']);
    }
    
    /** @test */
    public function it_calculates_vat_inclusive_correctly()
    {
        $result = TaxCalculator::calculateVAT(109000, 9, 'inclusive');
        
        $this->assertEquals(9000, $result['tax_amount']);
        $this->assertEquals(109000, $result['total_amount']);
        $this->assertEquals(100000, $result['base_amount']);
        $this->assertEquals(9, $result['tax_rate']);
        $this->assertEquals('inclusive', $result['method']);
    }
    
    /** @test */
    public function it_calculates_zero_rate_vat()
    {
        $result = TaxCalculator::calculateVAT(100000, 0, 'exclusive');
        
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(100000, $result['total_amount']);
        $this->assertEquals(100000, $result['base_amount']);
        $this->assertTrue(TaxCalculator::isExempt($result['tax_rate']));
    }
    
    /** @test */
    public function it_calculates_income_tax_correctly()
    {
        $result = TaxCalculator::calculateIncomeTax(500000, 25);
        
        $this->assertEquals(125000, $result['tax_amount']);
        $this->assertEquals(375000, $result['net_income']);
        $this->assertEquals(25, $result['tax_rate']);
    }
    
    /** @test */
    public function it_applies_rounding_correctly()
    {
        // Default: round
        $rounded = TaxCalculator::applyRounding(1234.56);
        $this->assertEquals(1235, $rounded);
        
        $rounded = TaxCalculator::applyRounding(1234.44);
        $this->assertEquals(1234, $rounded);
    }
    
    /** @test */
    public function it_calculates_multiple_items()
    {
        $items = [
            ['amount' => 100000, 'tax_rate' => 9],
            ['amount' => 50000, 'tax_rate' => 5],
            ['amount' => 30000, 'tax_rate' => 0],
        ];
        
        $result = TaxCalculator::calculateMultipleItems($items);
        
        $this->assertEquals(180000, $result['total_base']);
        $this->assertEquals(11500, $result['total_tax']); // 9000 + 2500 + 0
        $this->assertEquals(191500, $result['grand_total']);
        $this->assertCount(3, $result['items']);
    }
    
    /** @test */
    public function it_detects_tax_exemption()
    {
        $this->assertTrue(TaxCalculator::isExempt(0));
        $this->assertFalse(TaxCalculator::isExempt(9));
        $this->assertFalse(TaxCalculator::isExempt(5));
    }
    
    /** @test */
    public function it_handles_negative_amounts_gracefully()
    {
        $result = TaxCalculator::calculateVAT(-100, 9);
        
        // مقادیر منفی باید 0 برگردانند
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(0, $result['total_amount']);
    }
    
    /** @test */
    public function it_validates_tax_rate_bounds()
    {
        $this->expectException(\InvalidArgumentException::class);
        TaxCalculator::calculateVAT(100000, 150); // > 100%
    }
    
    /** @test */
    public function it_calculates_large_amounts_accurately()
    {
        $result = TaxCalculator::calculateVAT(999999999, 9, 'exclusive');
        
        $expectedTax = 89999999.91; // 999999999 * 0.09
        $this->assertEquals(90000000, $result['tax_amount']); // rounded
        $this->assertEquals(1089999999, $result['total_amount']);
    }
}
