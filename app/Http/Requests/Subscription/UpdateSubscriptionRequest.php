<?php
namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'service_id' => 'sometimes|exists:services,id',
            'plan_id' => 'nullable|exists:plans,id',
            'start_date' => 'sometimes|date',
            'starts_at' => 'sometimes|date',
            'next_billing_date' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'billing_cycle' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'partial_payment' => 'sometimes|numeric',
            'status' => 'sometimes|string',
            'unique_identifier' => 'sometimes|string|nullable',
            'auto_renew' => 'sometimes|boolean',
            'renewal_type' => 'sometimes|string',
            'notes' => 'nullable|string',
        ];
    }
}
