<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\ImageService;

/**
 * أكشن إنشاء علامة تجارية جديدة مع التحقق من التشابه (Fuzzy Check)
 */
class CreateBrandAction extends BaseAction
{
    public function handle(array $data = []): Brand
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $companyId = $authUser->company_id;

        // التحقق من الصلاحيات
        if (!$authUser->hasPermissionTo(perm_key('admin.super')) && 
            !$authUser->hasPermissionTo(perm_key('brands.create')) && 
            !$authUser->hasPermissionTo(perm_key('admin.company'))) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لإنشاء علامات تجارية.");
        }

        return DB::transaction(function () use ($data, $authUser, $companyId) {
            $name = $data['name'];
            $slug = Str::slug($name);

            // 1. البحث عن علامة تجارية موجودة بنفس الـ slug أو الاسم/المرادفات
            $existing = Brand::where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where(function ($q) use ($name, $slug) {
                $q->where('slug', $slug)
                  ->orWhere('name', 'LIKE', $name)
                  ->orWhereJsonContains('synonyms', strtolower($name));
            })->first();

            if ($existing) {
                return $existing;
            }

            // 2. فحص التشابه المتقدم (Fuzzy Check)
            $allItems = Brand::where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })->select('id', 'name', 'synonyms', 'company_id')->limit(500)->get();

            $similar = find_highly_similar_item($allItems, $name, ['name', 'synonyms'], 90);

            if ($similar) {
                return $similar;
            }

            // 3. إنشاء العلامة التجارية
            $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($data['company_id']))
                ? $data['company_id']
                : $companyId;

            $data['company_id'] = $brandCompanyId;
            $data['created_by'] = $authUser->id;
            $data['slug'] = $slug;

            $brand = Brand::create($data);

            if (!empty($data['image_id'])) {
                ImageService::attachImagesToModel([$data['image_id']], $brand, 'logo');
            }

            return $brand;
        });
    }
}
