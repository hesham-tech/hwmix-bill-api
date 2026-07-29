<?php

namespace App\Http\Requests\PartnerOperation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * فئة التحقق من صحة بيانات إنشاء عملية مالية للشركاء
 */
class StorePartnerOperationRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحًا له بإجراء هذا الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطلوبة
     */
    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'integer', 'exists:users,id'],
            'cashbox_id' => ['required', 'integer', 'exists:cash_boxes,id'],
            'type' => [
                'required',
                'string',
                'in:capital_increase,capital_withdrawal,partner_loan_given,partner_loan_repaid,profit_distribution,loss_coverage',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'operation_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'partner_id.required' => 'يرجى تحديد الشريك المعني بالعملية.',
            'partner_id.exists' => 'الشريك المحدد غير موجود بالنظام.',
            'cashbox_id.required' => 'يرجى تحديد الخزنة المؤثرة.',
            'cashbox_id.exists' => 'الخزنة المحددة غير موجودة بالنظام.',
            'type.required' => 'يرجى تحديد نوع عملية الشريك.',
            'type.in' => 'نوع عملية الشريك غير مدعوم.',
            'amount.required' => 'يرجى إدخال مبلغ العملية.',
            'amount.gt' => 'يجب أن يكون مبلغ العملية أكبر من صفر.',
        ];
    }
}
