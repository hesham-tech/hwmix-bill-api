<?php

namespace App\Http\Resources\User;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class UserWithPermissionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'balance' => $this->balance,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'settings' => $this->settings,
            'cash_box_id' => $this->getDefaultCashBoxForCompany()?->id,
            'company_logo' => $this->whenLoaded('company', fn() => $this->company->logo?->url),
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'customer_type' => $this->customer_type,
            'has_installments' => $this->whenLoaded('installments', fn() => $this->installments()->exists(), false),
            'cashBoxDefault' => new CashBoxResource($this->whenLoaded('cashBoxDefault')),
            // الشركات التي يمكن للمستخدم الوصول إليها
            'companies' => CompanyResource::collection($this->whenLoaded('companies', fn() => $this->getVisibleCompaniesForUser(), collect())),
            'cashBoxes' => CashBoxResource::collection($this->whenLoaded('cashBoxes')),

            // الصلاحيات والادوار
            'roles' => $this->getRolesWithPermissions(),
            'permissions' => $this->resource->hasPermissionTo(perm_key('admin.super'))
                ? \Spatie\Permission\Models\Permission::all()->pluck('name')
                : $this->getAllPermissions()->pluck('name'),
            'direct_permissions' => $this->resource->hasPermissionTo(perm_key('admin.super'))
                ? collect([perm_key('admin.super')])
                : $this->getDirectPermissions()->pluck('name'),

            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }

    protected function getVisibleCompaniesForUser()
    {
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return Company::all();
        }
        return $this->companies;
    }
}
