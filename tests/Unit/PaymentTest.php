<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\InvoiceCalculator;
use App\Services\TaxCalculator;

class PaymentTest extends TestCase
{
    /**
     * اختبار المتبقي بعد دفعات متعددة
     */
    public function test_payment_logic()
    {
        $calculator = new InvoiceCalculator(new TaxCalculator());

        $invoiceData = [
            'gross_amount' => 1000,
            'total_discount' => 100,
            'net_amount' => 900,
            'paid_amount' => 500,
            'items' => []
        ];

        $result = $calculator->calculateTotals([], $invoiceData);

        $this->assertEquals(400, $result['remaining_amount']);
    }
}
