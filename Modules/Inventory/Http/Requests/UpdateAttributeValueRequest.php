<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'color' => 'sometimes|nullable|string|max:255',
            'attribute_id' => 'sometimes|required|exists:attributes,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
        ];
    }
}
