<?php

namespace App\Services;

class InvoiceCalculator
{
    private TaxCalculator $taxCalculator;

    public function __construct(TaxCalculator $taxCalculator)
    {
        $this->taxCalculator = $taxCalculator;
    }

    /**
     * حساب إجماليات الفاتورة
     *
     * @param array $items العناصر
     * @param array $invoiceData بيانات الفاتورة
     * @return array
     */
    public function calculateTotals(array $items, array $invoiceData = []): array
    {
        $defaultTaxRate = $invoiceData['tax_rate'] ?? 0;
        $taxInclusive = $invoiceData['tax_inclusive'] ?? false;
        $invoiceDiscount = $invoiceData['total_discount'] ?? 0;

        // تطبيق الضريبة على العناصر
        $calculatedItems = $this->taxCalculator->applyTaxToItems($items, $defaultTaxRate, $taxInclusive);

        // حساب الإجماليات
        $grossAmount = collect($calculatedItems)->sum('total');
        $totalTax = $this->taxCalculator->calculateInvoiceTax($calculatedItems);

        // الصافي = الإجمالي - خصم الفاتورة
        // (الضريبة محسوبة بالفعل في كل عنصر)
        $netAmount = $grossAmount - $invoiceDiscount;

        $previousBalance = $invoiceData['previous_balance'] ?? 0;
        $totalRequired = $netAmount - $previousBalance;
        $remainingAmount = $totalRequired - ($invoiceData['paid_amount'] ?? 0);

        return [
            'items' => $calculatedItems,
            'gross_amount' => round($grossAmount, 2),
            'total_discount' => round($invoiceDiscount, 2),
            'total_tax' => round($totalTax, 2),
            'net_amount' => round($netAmount, 2),
            'paid_amount' => $invoiceData['paid_amount'] ?? 0,
            'remaining_amount' => round($remainingAmount, 2),
        ];
    }

    /**
     * حساب المبلغ المتبقي
     *
     * @param float $netAmount
     * @param float $paidAmount
     * @param float $previousBalance
     * @return float
     */
    public function calculateRemaining(float $netAmount, float $paidAmount, float $previousBalance = 0): float
    {
        $totalRequired = $netAmount - $previousBalance;
        return round($totalRequired - $paidAmount, 2);
    }

    /**
     * التحقق من صحة الحسابات
     *
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCalculations(array $data): array
    {
        $errors = [];

        // التحقق من gross_amount
        $calculatedGross = collect($data['items'])->sum('total');
        if (abs($calculatedGross - $data['gross_amount']) > 0.01) {
            $errors[] = "gross_amount غير صحيح. المتوقع: {$calculatedGross}, المستلم: {$data['gross_amount']}";
        }

        // التحقق من net_amount
        $expectedNet = $data['gross_amount'] - $data['total_discount'];
        if (abs($expectedNet - $data['net_amount']) > 0.01) {
            $errors[] = "net_amount غير صحيح. المتوقع: {$expectedNet}, المستلم: {$data['net_amount']}";
        }

        // التحقق من remaining_amount
        $previousBalance = $data['previous_balance'] ?? 0;
        $expectedRemaining = ($data['net_amount'] - $previousBalance) - $data['paid_amount'];
        if (isset($data['remaining_amount']) && abs($expectedRemaining - $data['remaining_amount']) > 0.01) {
            $errors[] = "remaining_amount غير صحيح. المتوقع: {$expectedRemaining}, المستلم: {$data['remaining_amount']}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
