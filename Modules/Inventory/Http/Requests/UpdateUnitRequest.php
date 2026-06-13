<?php
// التحقق من صحة بيانات تعديل وحدة قياس
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_group_id' => 'sometimes|exists:unit_groups,id',
            'name'          => 'sometimes|string|max:100',
            'code'          => 'sometimes|string|max:20',
            'decimal_places' => 'sometimes|integer|min:0|max:6',
            'is_active'     => 'sometimes|boolean',
        ];
    }
}
