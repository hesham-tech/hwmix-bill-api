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
            'service_id' => 'nullable|exists:services,id',
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'nullable|date',
            'starts_at' => 'required|date',
            'next_billing_date' => 'nullable|date',
            'ends_at' => 'required|date',
            'billing_cycle' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}
