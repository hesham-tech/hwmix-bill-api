<?php

namespace App\Services;

class TaxCalculator
{
    /**
     * حساب الضريبة لعنصر واحد
     *
     * @param float $quantity الكمية
     * @param float $unitPrice السعر للوحدة
     * @param float $discount الخصم
     * @param float $taxRate نسبة الضريبة (%)
     * @param bool $taxInclusive هل الضريبة مضمنة في السعر؟
     * @return array
     */
    public function calculateItemTax(
        float $quantity,
        float $unitPrice,
        float $discount = 0,
        float $taxRate = 0,
        bool $taxInclusive = false
    ): array {
        // السعر قبل الخصم
        $grossAmount = $quantity * $unitPrice;

        // السعر بعد الخصم
        $amountAfterDiscount = $grossAmount - $discount;

        if ($taxInclusive) {
            // الضريبة مضمنة في السعر
            // السعر المدخل يتضمن الضريبة، نحتاج لاستخراجها
            $taxAmount = $amountAfterDiscount - ($amountAfterDiscount / (1 + ($taxRate / 100)));
            $subtotal = $amountAfterDiscount - $taxAmount;
        } else {
            // الضريبة تُضاف على السعر
            $subtotal = $amountAfterDiscount;
            $taxAmount = $subtotal * ($taxRate / 100);
        }

        $total = $subtotal + $taxAmount;

        return [
            'gross_amount' => round($grossAmount, 2),
            'discount' => round($discount, 2),
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * حساب إجمالي الضريبة للفاتورة
     *
     * @param array $items العناصر
     * @return float
     */
    public function calculateInvoiceTax(array $items): float
    {
        $totalTax = 0;

        foreach ($items as $item) {
            $totalTax += $item['tax_amount'] ?? 0;
        }

        return round($totalTax, 2);
    }

    /**
     * تطبيق نسبة ضريبة موحدة على جميع العناصر
     *
     * @param array $items
     * @param float $defaultTaxRate
     * @param bool $taxInclusive
     * @return array
     */
    public function applyTaxToItems(array $items, float $defaultTaxRate = 0, bool $taxInclusive = false): array
    {
        $calculatedItems = [];

        foreach ($items as $item) {
            $itemTaxRate = $item['tax_rate'] ?? $defaultTaxRate;

            $calculated = $this->calculateItemTax(
                $item['quantity'],
                $item['unit_price'],
                $item['discount'] ?? 0,
                $itemTaxRate,
                $taxInclusive
            );

            $calculatedItems[] = array_merge($item, $calculated);
        }

        return $calculatedItems;
    }
}
