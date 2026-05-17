<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'created_by' => 'sometimes|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_id' => 'nullable|exists:images,id',
            'active' => 'nullable|boolean',
        ];
    }
}
