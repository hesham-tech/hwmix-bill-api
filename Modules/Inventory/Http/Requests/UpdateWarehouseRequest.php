<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'latitude'=>'nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|numeric|between:-180,180','location_confirmed'=>'required_with:latitude,longitude|boolean',
            'description' => 'sometimes|nullable|string',
            'manager' => 'sometimes|nullable|string|max:255',
            'capacity' => 'sometimes|nullable|integer|min:0',
            'status' => 'sometimes|required|in:active,inactive',
            'is_default' => 'sometimes|boolean',
            'company_id' => 'sometimes|required|exists:companies,id',
            'created_by' => 'sometimes|required|exists:users,id',
        ];
    }
}
