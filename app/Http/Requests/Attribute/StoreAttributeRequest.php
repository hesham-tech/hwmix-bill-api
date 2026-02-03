<?php

namespace App\Http\Requests\Attribute;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'value' => 'nullable|string|max:255',
            'values' => 'nullable|array',
            'values.*.name' => 'required_with:values|string|max:255',
            'values.*.color' => 'nullable|string|max:255',
            'values.*.value' => 'nullable|string|max:255', // keeping both for compatibility if needed elsewhere
        ];
    }
}
