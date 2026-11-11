<?php
namespace Modules\Store\Services;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
class StoreVendorRecipients {
    public function eligible(User $user,int $companyId): bool {
        if(!$user->companies()->where('companies.id',$companyId)->wherePivot('status','active')->exists())return false;
        $registrar=app(PermissionRegistrar::class);$previous=$registrar->getPermissionsTeamId();
        try{
            $registrar->setPermissionsTeamId($companyId);$user->unsetRelation('roles')->unsetRelation('permissions');
            return $user->checkPermissionTo('admin.super')||$user->checkPermissionTo('admin.company')||$user->checkPermissionTo('store.vendor.orders.view');
        }finally{$registrar->setPermissionsTeamId($previous);$user->unsetRelation('roles')->unsetRelation('permissions');}
    }
    public function members(int $companyId) {
        return User::withoutGlobalScopes()->whereHas('companies',fn($q)=>$q->where('companies.id',$companyId)->where('company_user.status','active'))
            ->get()->filter(fn($user)=>$this->eligible($user,$companyId));
    }
}
