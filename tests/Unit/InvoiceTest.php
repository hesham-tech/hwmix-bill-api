<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\InvoiceCalculator;
use App\Services\TaxCalculator;

class InvoiceTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $taxCalculator = new TaxCalculator();
        $this->calculator = new InvoiceCalculator($taxCalculator);
    }

    /**
     * اختبار حساب إجمالي الفواتير بدون ضريبة
     */
    public function test_calculate_totals_without_tax()
    {
        $items = [
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 0],
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
        ];

        $result = $this->calculator->calculateTotals($items);

        $this->assertEquals(200, $result['gross_amount']);
        $this->assertEquals(200, $result['net_amount']);
        $this->assertEquals(0, $result['total_tax']);
    }

    /**
     * اختبار حساب الفواتير مع ضريبة مضافة (Exclusive)
     */
    public function test_calculate_totals_with_exclusive_tax()
    {
        $items = [
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 14], // Total should be 114
        ];

        $result = $this->calculator->calculateTotals($items, ['tax_inclusive' => false]);

        $this->assertEquals(114, $result['gross_amount']);
        $this->assertEquals(14, $result['total_tax']);
        $this->assertEquals(114, $result['net_amount']);
    }

    /**
     * اختبار حساب الفواتير مع ضريبة شاملة (Inclusive)
     */
    public function test_calculate_totals_with_inclusive_tax()
    {
        $items = [
            ['quantity' => 1, 'unit_price' => 114, 'tax_rate' => 14], // Unit price includes 14% tax. Base is 100.
        ];

        $result = $this->calculator->calculateTotals($items, ['tax_inclusive' => true]);

        $this->assertEquals(114, $result['gross_amount']);
        $this->assertEquals(14, $result['total_tax']);
        $this->assertEquals(114, $result['net_amount']);
    }

    /**
     * اختبار صحة حساب المبلغ المتبقي
     */
    public function test_calculate_remaining_amount()
    {
        $netAmount = 1000.00;
        $paidAmount = 400.00;

        $remaining = $this->calculator->calculateRemaining($netAmount, $paidAmount);

        $this->assertEquals(600.00, $remaining);
    }
}
