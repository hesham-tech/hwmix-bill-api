<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'duration' => 'nullable|integer|min:1',
            'duration_unit' => 'nullable|string|max:20',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'max_users' => 'nullable|integer',
            'max_products' => 'nullable|integer',
            'max_invoices' => 'nullable|integer',
            'type' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:255',
        ];
    }
}
