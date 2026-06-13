<?php
// التحقق من صحة بيانات إضافة معامل تحويل بين وحدات القياس
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_group_id' => 'required|exists:unit_groups,id',
            'from_unit_id'  => 'required|exists:units,id',
            'to_unit_id'    => 'required|exists:units,id|different:from_unit_id',
            'factor'        => 'required|numeric|min:0.000001',
        ];
    }
}
