<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'image_id' => 'nullable|exists:images,id',
            'active' => 'nullable|boolean',
        ];
    }
}
