<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'cash_box_type_id' => ['required', 'exists:cash_box_types,id'],
            'is_default' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'created_by' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'account_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
