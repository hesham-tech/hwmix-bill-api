<?php
namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'payment_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'cash_amount' => 'required|numeric|min:0',
            'credit_amount' => 'required|numeric|min:0',
            'method' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_split' => 'nullable|boolean',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_box_id' => 'required|exists:cash_boxes,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'use_credit' => 'nullable|boolean',
        ];
    }
}
