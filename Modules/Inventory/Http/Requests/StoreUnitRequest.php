<?php
// التحقق من صحة بيانات إضافة وحدة قياس
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_group_id' => 'required|exists:unit_groups,id',
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:20',
            'decimal_places' => 'required|integer|min:0|max:6',
            'is_active'     => 'sometimes|boolean',
        ];
    }
}
