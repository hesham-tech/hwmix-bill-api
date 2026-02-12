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

            $isAdmin = $authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('admin.company'));
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));

            if ($isAdmin) {
                // For Admins (Super or Company)
                if (!$isSuperAdmin) {
                    // If Company Admin but NOT Super Admin: filter out any super admin keys
                    $permissionsConfig = collect($permissionsConfig)->map(function ($group) {
                        if (!is_array($group))
                            return null;

                        $filteredGroup = collect($group)->filter(function ($p, $pKey) {
                            if ($pKey === 'name')
                                return true;
                            if (is_array($p) && isset($p['key'])) {
                                // Hide anything related to super admin
                                return !str_contains($p['key'], 'admin.super');
                            }
                            return true;
                        });

                        return $filteredGroup->count() > 1 ? $filteredGroup->all() : null;
                    })->filter()->all();
                }
            } else {
                // If not admin, only show permissions they actually have
                $userPermissions = $authUser->getAllPermissions()->pluck('name')->toArray();

                $permissionsConfig = collect($permissionsConfig)->map(function ($group) use ($userPermissions) {
                    if (!is_array($group))
                        return null;

                    $filteredGroup = collect($group)->filter(function ($p, $pKey) use ($userPermissions) {
                        return $pKey === 'name' || (is_array($p) && isset($p['key']) && in_array($p['key'], $userPermissions));
                    });

                    return $filteredGroup->count() > 1 ? $filteredGroup->all() : null;
                })->filter()->all();
            }

            $response = empty($permissionsConfig) ? (object) [] : $permissionsConfig;
            return api_success($response, 'تم جلب تعريفات الصلاحيات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
