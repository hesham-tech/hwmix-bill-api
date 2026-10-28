<?php

namespace Modules\Inventory\Services;

use App\Models\Company;
use App\Services\CurrentCompanyResolver;
use Illuminate\Support\Facades\DB;

class LabelTemplateAccess
{
    public static function can(string $permission): bool
    {
        $names = auth()->user()?->getAllPermissions()->pluck('name')->all() ?? [];

        return (bool) array_intersect(['admin.super', 'admin.company', $permission], $names);
    }

    public static function require(string $permission): void
    {
        abort_unless(self::can($permission), 403, 'لا تملك الصلاحية المطلوبة لهذه العملية.');
    }

    public static function company(): Company
    {
        $id = app(CurrentCompanyResolver::class)->resolve();
        abort_unless($id, 409, 'اختر الشركة النشطة أولًا.');
        $company = Company::withoutGlobalScopes()->findOrFail($id);
        $user = auth()->user();
        abort_unless((int) $user->active_company_id === (int) $id, 409, 'غيّر الشركة النشطة من النظام قبل استخدام قوالبها.');
        $super = in_array('admin.super', $user->getAllPermissions()->pluck('name')->all(), true);
        $member = (int) $user->company_id === (int) $id || (int) $company->created_by === (int) $user->id
            || DB::table('company_user')->where('company_id', $id)->where('user_id', $user->id)->exists();
        abort_unless($super || $member, 403, 'لا يمكنك الوصول إلى قوالب هذه الشركة.');

        return $company;
    }

    public static function currency(Company $company): ?string
    {
        $value = data_get($company->settings, 'currency_symbol') ?: data_get($company->settings, 'currency.symbol') ?: data_get($company->settings, 'currency');

        return is_string($value) && mb_strlen(trim($value)) <= 20 && trim($value) !== '' ? trim($value) : null;
    }

    public static function fields(): array
    {
        $groups = [
            'company' => ['currency' => 'عملة الشركة', 'name' => 'اسم الشركة', 'address' => 'عنوان الشركة', 'phone' => 'هاتف الشركة', 'email' => 'بريد الشركة', 'website' => 'موقع الشركة', 'tax_number' => 'الرقم الضريبي', 'logo' => 'شعار الشركة'],
            'product' => ['name' => 'اسم المنتج', 'slug' => 'معرّف المنتج في الرابط', 'description' => 'الوصف المختصر', 'description_long' => 'الوصف التفصيلي', 'category' => 'الفئة', 'brand' => 'العلامة التجارية', 'image' => 'صورة المنتج', 'type' => 'نوع المنتج'],
            'variant' => ['sku' => 'كود الصنف SKU', 'barcode' => 'الباركود', 'attributes' => 'السمات: اللون والمقاس', 'retail_price' => 'سعر البيع الحالي', 'discount' => 'الخصم', 'tax' => 'الضريبة', 'weight' => 'الوزن', 'dimensions' => 'الأبعاد', 'image' => 'صورة المتغير'],
            'unit' => ['name' => 'اسم الوحدة', 'conversion_factor' => 'معامل الوحدة', 'price' => 'سعر بيع الوحدة'],
            'invoice' => ['unit_price' => 'سعر بند الفاتورة', 'number' => 'رقم الفاتورة', 'date' => 'تاريخ الفاتورة'],
            'print' => ['date' => 'تاريخ الطباعة'],
        ];
        if (self::can('products.view_wholesale_price')) {
            $groups['variant']['wholesale_price'] = 'سعر الجملة';
        }
        if (self::can('products.view_purchase_price')) {
            $groups['variant']['purchase_price'] = 'سعر الشراء';
        }
        $fields = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $key => $title) {
                $field = "$group.$key";
                $type = in_array($key, ['image', 'logo']) ? 'image' : (in_array($key, ['retail_price', 'wholesale_price', 'purchase_price', 'unit_price', 'price', 'discount', 'tax', 'weight', 'conversion_factor']) ? 'number' : (in_array($key, ['date']) ? 'date' : 'text'));
                $fields[] = ['key' => $field, 'title' => $title, 'group' => $group, 'type' => $type, 'monetary' => in_array($field, ['variant.retail_price', 'variant.wholesale_price', 'variant.purchase_price', 'unit.price', 'invoice.unit_price']), 'requiresInvoice' => $group === 'invoice'];
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('attributes')) {
            $companyId = app(CurrentCompanyResolver::class)->resolve();
            $attributes = DB::table('attributes')->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))->get(['id', 'name']);
            foreach ($attributes as $attribute) {
                $fields[] = ['key' => 'attribute.'.$attribute->id, 'title' => 'السمة: '.$attribute->name, 'group' => 'attribute', 'type' => 'text', 'requiresInvoice' => false];
            }
        }

        return $fields;
    }
}
