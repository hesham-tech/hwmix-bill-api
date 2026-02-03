<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidInvoiceTotals implements ValidationRule
{
    private $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // التحقق من أن items موجود وهو array
        if (!is_array($this->items) || empty($this->items)) {
            return; // سيتم التعامل مع هذا في validation آخر
        }

        // حساب gross_amount من العناصر
        $calculatedGross = collect($this->items)->sum(function ($item) {
            return $item['total'] ?? 0;
        });

        // التحقق من التطابق (مع هامش خطأ صغير للـ rounding)
        if (abs($calculatedGross - $value) > 0.01) {
            $fail("إجمالي الفاتورة ({$value}) لا يطابق مجموع العناصر ({$calculatedGross}).");
        }
    }
}
