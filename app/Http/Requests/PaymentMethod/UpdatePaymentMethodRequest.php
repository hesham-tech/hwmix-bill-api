<?php

namespace App\Http\Requests\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = Auth::user()->company_id;
        $paymentMethodId = $this->route('payment_method');

        return [
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('payment_methods')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($paymentMethodId),
            ],
            'active' => 'sometimes|boolean',
            'company_id' => 'nullable|exists:companies,id',
            'image_id' => 'nullable|exists:images,id',
        ];
    }
}
