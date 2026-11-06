<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\Auth;
final class StoreAccess {
    public function canPlatform(string $permission='store.settings.manage'): bool {
        $user=Auth::user();
        return $user&&($user->checkPermissionTo(perm_key('admin.super'))||
            ((int)$user->active_company_id===(int)config('app.master_company_id',1)&&$user->checkPermissionTo(perm_key($permission))));
    }
    public function platform(string $permission='store.settings.manage'): void {
        abort_unless($this->canPlatform($permission),403);
    }
    public function seller(string $permission='store.vendor.orders.view'): int {
        $user=Auth::user(); $companyId=(int)$user?->active_company_id;
        abort_unless($user&&($user->checkPermissionTo(perm_key('admin.super'))||$user->companies()->where('companies.id',$companyId)->wherePivot('status','active')->exists()),403);
        abort_unless($companyId&&$user&&($user->checkPermissionTo(perm_key('admin.super'))||
            $user->checkPermissionTo(perm_key('admin.company'))||$user->checkPermissionTo(perm_key($permission))),403);
        return $companyId;
    }
}
