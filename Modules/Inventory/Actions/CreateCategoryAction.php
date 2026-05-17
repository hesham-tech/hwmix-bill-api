<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\ImageService;

/**
 * أكشن إنشاء قسم جديد
 */
class CreateCategoryAction extends BaseAction
{
    public function handle(array $data = []): Category
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $companyId = $authUser->company_id;

        if (!$authUser->hasPermissionTo(perm_key('admin.super')) && 
            !$authUser->hasPermissionTo(perm_key('categories.create')) && 
            !$authUser->hasPermissionTo(perm_key('admin.company'))) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لإنشاء أقسام.");
        }

        return DB::transaction(function () use ($data, $authUser, $companyId) {
            $name = $data['name'];
            $slug = Str::slug($name);

            // التحقق من وجود قسم مشابه
            $existing = Category::where(function ($q) use ($companyId) {
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

            $categoryCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($data['company_id']))
                ? $data['company_id']
                : $companyId;

            $data['company_id'] = $categoryCompanyId;
            $data['created_by'] = $authUser->id;
            $data['slug'] = $slug;

            $category = Category::create($data);

            if (!empty($data['image_id'])) {
                ImageService::attachImagesToModel([$data['image_id']], $category, 'icon');
            }

            return $category;
        });
    }
}
