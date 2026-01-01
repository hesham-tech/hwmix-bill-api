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

        $items = [
            ['product_id' => 1, 'variant_id' => 1, 'name' => 'Test', 'quantity' => 1, 'unit_price' => 1000, 'total' => 1000]
        ];

        $invoiceData = [
            'gross_amount' => 1000,
            'total_discount' => 100,
            'net_amount' => 900,
            'paid_amount' => 500,
            'items' => $items
        ];

        $result = $calculator->calculateTotals($items, $invoiceData);

        $this->assertEquals(400, $result['remaining_amount']);
    }
}
