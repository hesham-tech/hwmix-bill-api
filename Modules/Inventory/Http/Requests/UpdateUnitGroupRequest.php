<?php
// التحقق من صحة بيانات تعديل مجموعة وحدات قياس
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:weight,length,volume,area,count,custom',
        ];
    }
}
