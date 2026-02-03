<?php
namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'plan_id' => 'nullable|exists:plans,id',
            'start_date' => 'nullable|date',
            'starts_at' => 'required|date',
            'next_billing_date' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'billing_cycle' => 'required|string',
            'price' => 'required|numeric|min:0',
            'partial_payment' => 'nullable|numeric',
            'status' => 'required|string',
            'unique_identifier' => 'nullable|string',
            'auto_renew' => 'nullable|boolean',
            'renewal_type' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
