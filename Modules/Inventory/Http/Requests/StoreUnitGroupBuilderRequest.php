<?php
// كلاس التحقق من صحة بيانات إنشاء مجموعة وحدات كاملة (مجموعة + وحدات + تحويلات) دفعة واحدة
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitGroupBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // بيانات المجموعة
            'name'                              => 'required|string|max:100',
            'type'                              => 'required|in:weight,length,volume,area,count,custom',

            // الوحدات: مطلوب وحدة واحدة على الأقل
            'units'                             => 'required|array|min:1',
            'units.*.temp_uuid'                 => 'required|string|distinct',
            'units.*.name'                      => 'required|string|max:100',
            'units.*.code'                      => 'required|string|max:20',
            'units.*.decimal_places'            => 'required|integer|min:0|max:6',
            'units.*.is_active'                 => 'boolean',

            // التحويلات: اختيارية
            'conversions'                       => 'nullable|array',
            'conversions.*.from_unit_temp_uuid' => 'required_with:conversions|string',
            'conversions.*.to_unit_temp_uuid'   => 'required_with:conversions|string|different:conversions.*.from_unit_temp_uuid',
            'conversions.*.factor'              => 'required_with:conversions|numeric|min:0.000001',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                              => 'اسم المجموعة مطلوب.',
            'type.required'                              => 'نوع المجموعة مطلوب.',
            'type.in'                                    => 'نوع المجموعة غير صالح.',
            'units.required'                             => 'يجب إضافة وحدة واحدة على الأقل.',
            'units.min'                                  => 'يجب إضافة وحدة واحدة على الأقل.',
            'units.*.temp_uuid.required'                 => 'المعرف المؤقت للوحدة مطلوب.',
            'units.*.temp_uuid.distinct'                 => 'المعرفات المؤقتة للوحدات يجب أن تكون فريدة.',
            'units.*.name.required'                      => 'اسم الوحدة مطلوب.',
            'units.*.code.required'                      => 'رمز الوحدة مطلوب.',
            'units.*.decimal_places.required'            => 'عدد الخانات العشرية مطلوب.',
            'conversions.*.from_unit_temp_uuid.required' => 'وحدة المصدر مطلوبة في قاعدة التحويل.',
            'conversions.*.to_unit_temp_uuid.required'   => 'وحدة الهدف مطلوبة في قاعدة التحويل.',
            'conversions.*.factor.required'              => 'معامل التحويل مطلوب.',
            'conversions.*.factor.min'                   => 'معامل التحويل يجب أن يكون أكبر من صفر.',
        ];
    }
}
