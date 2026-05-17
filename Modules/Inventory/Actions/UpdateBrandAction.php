<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\ImageService;

/**
 * أكشن تحديث علامة تجارية
 */
class UpdateBrandAction extends BaseAction
{
    public function handle(array $data = []): Brand
    {
        $brand = $data['brand'];
        unset($data['brand']);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من الصلاحيات
        $canUpdate = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            $canUpdate = true;
        } elseif ($authUser->hasAnyPermission([perm_key('brands.update_all'), perm_key('admin.company')])) {
            $canUpdate = $brand->belongsToCurrentCompany();
        } elseif ($authUser->hasPermissionTo(perm_key('brands.update_children'))) {
            $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('brands.update_self'))) {
            $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
        }

        if (!$canUpdate) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لتحديث هذه العلامة التجارية.");
        }

        return DB::transaction(function () use ($data, $brand, $authUser) {
            $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($data['company_id']))
                ? $data['company_id']
                : $brand->company_id;

            if ($brandCompanyId != $brand->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                throw new \Illuminate\Auth\Access\AuthorizationException("لا يمكنك تغيير شركة العلامة التجارية.");
            }

            $data['company_id'] = $brandCompanyId;
            $data['updated_by'] = $authUser->id;

            $brand->update($data);

            if (isset($data['image_id'])) {
                $newImageIds = $data['image_id'] ? [$data['image_id']] : [];
                ImageService::syncImagesWithModel($newImageIds, $brand, 'logo');
            }

            return $brand;
        });
    }
}
