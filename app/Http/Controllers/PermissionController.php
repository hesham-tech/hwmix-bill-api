<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PermissionController extends Controller
{
    /**
     * @group 09. الأذونات والأمن
     * 
     * استعراض مفاتيح الصلاحيات
     * 
     * جلب جميع تعريفات الصلاحيات المتاحة في النظام من ملف الإعدادات.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
            $permissionsConfig = config('permissions_keys');

            $isAdmin = $authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')]);

            if (!$isAdmin) {
                $filteredConfig = [];
                foreach ($permissionsConfig as $groupKey => $group) {
                    $filteredGroup = [];
                    foreach ($group as $pKey => $p) {
                        if ($pKey === 'name') {
                            $filteredGroup[$pKey] = $p;
                            continue;
                        }

                        // Check if user has this exact permission
                        if ($authUser->hasPermissionTo($p['key'])) {
                            $filteredGroup[$pKey] = $p;
                        }
                    }

                    // Only add group if it has permissions (other than just 'name')
                    if (count($filteredGroup) > 1) {
                        $filteredConfig[$groupKey] = $filteredGroup;
                    }
                }
                $permissionsConfig = $filteredConfig;
            }

            if (empty($permissionsConfig)) {
                return api_success($permissionsConfig, 'لم يتم العثور على تعريفات صلاحيات متاحة لك.');
            } else {
                return api_success($permissionsConfig, 'تم جلب تعريفات الصلاحيات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
