<?php

namespace App\Http\Requests\CashBox;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashBoxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'cash_box_type_id' => ['sometimes', 'exists:cash_box_types,id'],
            'is_default' => ['sometimes', 'boolean'],
            'user_id' => ['sometimes', 'exists:users,id'],
            'created_by' => ['nullable', 'exists:users,id'],
            'company_id' => ['sometimes', 'exists:companies,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
