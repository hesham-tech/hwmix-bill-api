<?php
// متحكم إدارة وحدات القياس مع دعم CRUD كامل وحماية الوحدات المستخدمة
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreUnitRequest;
use Modules\Inventory\Http\Requests\UpdateUnitRequest;
use Modules\Inventory\Http\Resources\UnitResource;
use Modules\Inventory\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class UnitController extends Controller
{
    /**
     * عرض قائمة وحدات القياس المتاحة
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Unit::with('group');

            if (!$request->boolean('with_inactive', false)) {
                $query->where('is_active', true);
            }

            if ($request->filled('unit_group_id')) {
                $query->where('unit_group_id', $request->unit_group_id);
            }

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $units = $query->orderBy('name')->get();

            return api_success(UnitResource::collection($units), 'تم استرداد وحدات القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إنشاء وحدة قياس جديدة
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        try {
            $companyId = auth()->user()?->active_company_id;

            $unit = Unit::create([
                'unit_group_id'  => $request->unit_group_id,
                'name'           => $request->name,
                'code'           => $request->code,
                'decimal_places' => $request->decimal_places ?? 0,
                'is_active'      => $request->boolean('is_active', true),
                'company_id'     => $companyId,
                'created_by'     => auth()->id(),
            ]);

            return api_success(new UnitResource($unit->load('group')), 'تم إضافة وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض وحدة قياس محددة
     */
    public function show(Unit $unit): JsonResponse
    {
        try {
            return api_success(new UnitResource($unit->load('group')), 'تم استرداد وحدة القياس.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث وحدة قياس
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        try {
            // منع تعديل الوحدات العامة للسيستم من غير السوبر آدمن
            if (is_null($unit->company_id) && !auth()->user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعديل وحدات النظام الأساسية.', [], 403);
            }

            $unit->update($request->validated());

            return api_success(new UnitResource($unit->load('group')), 'تم تحديث وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف وحدة قياس — أو تعطيلها إذا كانت مستخدمة في عمليات فعلية
     */
    public function destroy(Unit $unit): JsonResponse
    {
        try {
            // منع حذف الوحدات العامة للسيستم
            if (is_null($unit->company_id)) {
                return api_error('لا يمكن حذف وحدات النظام الأساسية.', [], 403);
            }

            // فحص الاستخدام في كل جداول النظام
            $usageDetails = $this->checkUnitUsage($unit);

            if (!empty($usageDetails)) {
                // إذا كانت مستخدمة → تعطيل بدل الحذف
                $unit->update(['is_active' => false]);

                return api_success(
                    ['deactivated' => true, 'usage' => $usageDetails],
                    'لا يمكن حذف هذه الوحدة لأنها مستخدمة في ' . $this->formatUsageMessage($usageDetails) . '. تم تعطيلها بدلاً من حذفها.'
                );
            }

            $unit->delete();

            return api_success(null, 'تم حذف وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعطيل وحدة قياس يدوياً
     */
    public function deactivate(Unit $unit): JsonResponse
    {
        try {
            if (is_null($unit->company_id) && !auth()->user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعطيل وحدات النظام الأساسية.', [], 403);
            }

            $unit->update(['is_active' => false]);

            return api_success(new UnitResource($unit->load('group')), 'تم تعطيل وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * فحص كل مواضع استخدام الوحدة في النظام
     */
    private function checkUnitUsage(Unit $unit): array
    {
        $usage = [];

        // استخدام في جدول المنتجات
        $inProducts = DB::table('products')
            ->where(fn($q) => $q
                ->where('base_unit_id', $unit->id)
                ->orWhere('purchase_unit_id', $unit->id)
                ->orWhere('display_unit_id', $unit->id)
            )->count();
        if ($inProducts > 0) {
            $usage['products'] = $inProducts;
        }

        // استخدام في وحدات متغيرات المنتجات
        $inVariantUnits = DB::table('product_variant_units')
            ->where('unit_id', $unit->id)
            ->count();
        if ($inVariantUnits > 0) {
            $usage['product_variant_units'] = $inVariantUnits;
        }

        // استخدام في أسعار متغيرات المنتجات
        $inVariantPrices = DB::table('product_variant_unit_prices')
            ->where('unit_id', $unit->id)
            ->count();
        if ($inVariantPrices > 0) {
            $usage['product_variant_unit_prices'] = $inVariantPrices;
        }

        // استخدام في بنود الفواتير (بما فيها المحذوفة)
        $inInvoiceItems = DB::table('invoice_items')
            ->where('unit_id', $unit->id)
            ->count();
        if ($inInvoiceItems > 0) {
            $usage['invoice_items'] = $inInvoiceItems;
        }

        // استخدام في متغيرات المنتجات كـ base/purchase/display
        $inVariants = DB::table('product_variants')
            ->where(fn($q) => $q
                ->where('base_unit_id', $unit->id)
                ->orWhere('purchase_unit_id', $unit->id)
                ->orWhere('display_unit_id', $unit->id)
            )->count();
        if ($inVariants > 0) {
            $usage['product_variants'] = $inVariants;
        }

        return $usage;
    }

    /**
     * تحويل معلومات الاستخدام إلى رسالة واضحة للمستخدم
     */
    private function formatUsageMessage(array $usage): string
    {
        $parts = [];
        $map = [
            'products'                   => 'منتجات',
            'product_variants'           => 'متغيرات منتجات',
            'product_variant_units'      => 'وحدات بيع مرتبطة',
            'product_variant_unit_prices'=> 'أسعار وحدات',
            'invoice_items'              => 'بنود فواتير',
        ];
        foreach ($usage as $key => $count) {
            if (isset($map[$key])) {
                $parts[] = "{$count} {$map[$key]}";
            }
        }
        return implode(' و', $parts);
    }
}
