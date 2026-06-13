<?php
// التحقق من صحة بيانات إضافة مجموعة وحدات قياس جديدة
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:weight,length,volume,area,count,custom',
        ];
    }
}
