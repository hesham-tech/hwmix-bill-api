<?php

namespace App\Http\Resources\User;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'customer_type' => $this->customer_type,
            'position' => $this->position,
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),
            'company_logo' => $this->whenLoaded('company', fn() => $this->company->logo?->url),
            'cash_box_id' => $this->getDefaultCashBoxForCompany()?->id,
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'created_by' => $this->created_by,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'direct_permissions' => $this->whenLoaded('permissions', fn() => $this->getDirectPermissions()->pluck('name')),
            'companies' => CompanyResource::collection($this->whenLoaded('companies', fn() => $this->getVisibleCompaniesForUser(), collect())),
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }

    protected function getVisibleCompaniesForUser()
    {
        // Debugging: تحقق من محتوى $this->companies

        // Debugging: تأكد من أن $this->companies هو Collection فارغة إذا لم تكن هناك علاقات
        if ($this->companies->isEmpty()) {
        } else {
        }

        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return Company::all();
        }
        return $this->companies;
    }
}
