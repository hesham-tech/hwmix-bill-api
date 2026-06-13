<?php
// التحقق من صحة بيانات تعديل معامل تحويل بين وحدات القياس
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'factor' => 'required|numeric|min:0.000001',
        ];
    }
}
