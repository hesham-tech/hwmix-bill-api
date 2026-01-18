<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'nullable|integer',
            'invoice_type_id' => 'required|integer|exists:invoice_types,id',
            'invoice_type_code' => 'nullable|string',
            'status' => 'nullable|string|in:draft,confirmed,paid,partially_paid,overdue,canceled,refunded',

            // الحقول المالية
            'gross_amount' => 'required|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',

            // حقول الضرائب (جديدة)
            'total_tax' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_inclusive' => 'nullable|boolean',

            'net_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric',

            'round_step' => 'nullable|integer',

            'due_date' => 'nullable|date|after_or_equal:today',
            'issue_date' => 'nullable|date',
            'reference_number' => 'nullable|string',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_id' => 'required|integer|exists:users,id',

            'notes' => 'nullable|string',

            // العناصر
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',

            // حقول الضرائب للصنف
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.subtotal' => 'nullable|numeric|min:0',

            'items.*.total' => 'required|numeric|min:0',
            'items.*.attributes' => 'nullable|array',
            'items.*.attributes.*.id' => 'nullable|integer',
            'items.*.attributes.*.attribute_id' => 'nullable|integer',
            'items.*.attributes.*.attribute_value_id' => 'nullable|integer',
            'items.*.stocks' => 'nullable|array',
            'items.*.stocks.*.id' => 'nullable|integer',
            'items.*.stocks.*.quantity' => 'required|integer',
            'items.*.stocks.*.reserved' => 'nullable|integer',
            'items.*.stocks.*.min_quantity' => 'nullable|integer',
            'items.*.stocks.*.cost' => 'required|numeric',
            'items.*.stocks.*.batch' => 'nullable|string',
            'items.*.stocks.*.expiry' => 'nullable|date',
            'items.*.stocks.*.loc' => 'nullable|string',
            'items.*.stocks.*.status' => 'nullable|string',

            // خطة التقسيط
            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric',
            'installment_plan.number_of_installments' => 'nullable|integer|min:1',
            'installment_plan.installment_amount' => 'nullable|numeric',
            'installment_plan.total_amount' => 'nullable|numeric',
            'installment_plan.start_date' => 'nullable|date',
            'installment_plan.due_date' => 'nullable|date',
            'installment_plan.round_step' => 'nullable|integer',
        ];
    }
}
