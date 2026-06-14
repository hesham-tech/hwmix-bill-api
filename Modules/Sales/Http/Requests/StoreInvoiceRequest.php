<?php

namespace Modules\Sales\Http\Requests;

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
            'gross_amount' => 'required|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'total_tax' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_inclusive' => 'nullable|boolean',
            'net_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric',
            'previous_balance' => 'nullable|numeric',
            'round_step' => 'nullable|integer',
            'due_date' => 'nullable|date|after_or_equal:today',
            'issue_date' => 'nullable|date',
            'reference_number' => 'nullable|string',
            'warehouse_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('warehouses', 'id')->where('company_id', auth()->user()?->active_company_id)
            ],
            'to_warehouse_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('warehouses', 'id')->where('company_id', auth()->user()?->active_company_id)
            ],
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.subtotal' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'total_balance' => 'nullable|numeric',
            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric',
            'installment_plan.number_of_installments' => 'nullable|integer|min:1',
            'installment_plan.installment_amount' => 'nullable|numeric',
            'installment_plan.frequency' => 'nullable|string|in:monthly,weekly,biweekly,quarterly',
            'installment_plan.net_amount' => 'nullable|numeric',
            'installment_plan.interest_rate' => 'nullable|numeric',
            'installment_plan.interest_amount' => 'nullable|numeric',
            'installment_plan.total_amount' => 'nullable|numeric',
            'installment_plan.round_step' => 'nullable|integer',
            'installment_plan.start_date' => 'nullable|date',
        ];
    }

    /**
     * تحقق إضافي بعد التحقق الأولي لمنع التقسيط والديون للعميل النقدي الافتراضي.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $userId = $this->input('user_id');
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user && $user->isDefaultCashCustomer()) {
                    // 1. يمنع التقسيط للعميل النقدي الافتراضي
                    if ($this->has('installment_plan') && !empty($this->input('installment_plan'))) {
                        $validator->errors()->add('installment_plan', 'لا يمكن جدولة أقساط للعميل النقدي الافتراضي.');
                    }

                    // 2. يجب أن تكون الفاتورة مدفوعة بالكامل (لا يسمح بديون للعميل النقدي)
                    $netAmount = (float) $this->input('net_amount');
                    $paidAmount = (float) $this->input('paid_amount', 0);
                    if ($paidAmount < $netAmount) {
                        $validator->errors()->add('paid_amount', 'يجب دفع قيمة الفاتورة بالكامل للعميل النقدي الافتراضي.');
                    }
                }
            }
        });
    }
}
