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
     * تطبيق نسبة ضريبة موحدة على جميع العناصر مع دعم أوضاع الإدخال الثلاثة (الكمية / المبلغ / مختلط)
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
            
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : null;
            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $total = isset($item['total']) ? (float)$item['total'] : null;
            $discount = isset($item['discount']) ? (float)$item['discount'] : 0;

            if ($unitPrice <= 0) {
                $quantity = $quantity ?? 0.0;
                $total = $total ?? 0.0;
            } else {
                if (is_null($quantity) && !is_null($total)) {
                    // Amount Mode: حساب الكمية من المبلغ الإجمالي المدخل
                    if ($taxInclusive) {
                        $quantity = ($total + $discount) / $unitPrice;
                    } else {
                        $taxMultiplier = 1 + ($itemTaxRate / 100);
                        $quantity = (($total / $taxMultiplier) + $discount) / $unitPrice;
                    }
                }
            }

            if (is_null($quantity)) {
                $quantity = 0.0;
            }

            $calculated = $this->calculateItemTax(
                $quantity,
                $unitPrice,
                $discount,
                $itemTaxRate,
                $taxInclusive
            );

            // التحقق من التطابق في حالة mixed mode (إدخال كمية وإجمالي معاً)
            if (!is_null($total) && !is_null(isset($item['quantity']) ? $item['quantity'] : null) && abs($calculated['total'] - $total) > 0.05) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => ["قيمة الإجمالي المرسلة لا تتطابق مع الحسابات (المرسل: $total، المحسوب: {$calculated['total']})."]
                ]);
            }

            $calculatedItems[] = array_merge($item, $calculated, [
                'quantity' => $quantity
            ]);
        }

        return $calculatedItems;
    }
}
