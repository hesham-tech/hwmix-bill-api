<?php

namespace Modules\Inventory\Actions;

use Modules\Core\Actions\BaseAction;
use Modules\Inventory\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\ImageService;

/**
 * أكشن حذف علامة تجارية
 */
class DeleteBrandAction extends BaseAction
{
    public function handle(array $data = []): bool
    {
        $brand = $data['brand'];
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من الصلاحيات
        $canDelete = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            $canDelete = true;
        } elseif ($authUser->hasAnyPermission([perm_key('brands.delete_all'), perm_key('admin.company')])) {
            $canDelete = $brand->belongsToCurrentCompany();
        } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_children'))) {
            $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_self'))) {
            $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
        }

        if (!$canDelete) {
            throw new \Illuminate\Auth\Access\AuthorizationException("ليس لديك إذن لحذف هذه العلامة التجارية.");
        }

        return DB::transaction(function () use ($brand) {
            if ($brand->products()->exists()) {
                throw new \Exception("لا يمكن حذف العلامة التجارية. إنها مرتبطة بمنتج واحد أو أكثر.");
            }

            if ($brand->image) {
                ImageService::deleteImages([$brand->image->id]);
            }

            return $brand->delete();
        });
    }
}
