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
        // Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† ØªØ­Ø¯ÙŠØ¯ Ø´Ø±ÙƒØ© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù‚Ø¨Ù„ Ø¬Ù„Ø¨ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª (Ù…Ù‡Ù… Ù„Ù…Ø³Ø§Ø±Ø§Øª ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯Ø®ÙˆÙ„)
        if (config('permission.teams') && $this->active_company_id) {
            setPermissionsTeamId($this->active_company_id);
        }

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'balance' => $this->active_branch_balance,
            'active_branch_balance' => $this->active_branch_balance,
            'custody_balance' => $this->custody_balance,
            'total_branches_balance' => $this->total_branches_balance,
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
            'active_company_id' => $this->active_company_id,
            'is_active_company_deleted' => $this->active_company_id ? !\App\Models\Company::withoutGlobalScopes()->where('id', $this->active_company_id)->whereNull('deleted_at')->exists() : false,
            'created_by' => $this->created_by,
            'customer_type' => $this->customer_type,
            'is_staff_or_admin' => $this->isStaffOrAdmin(),
            'user_type' => $this->isStaffOrAdmin() ? 'staff' : 'customer',
            'has_installments' => $this->whenLoaded('installments', fn() => $this->installments()->exists(), false),
            'cashBoxDefault' => new CashBoxResource($this->getDefaultCashBoxForCompany()),
            // Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªÙŠ ÙŠÙ…ÙƒÙ† Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„ÙŠÙ‡Ø§
            'companies' => $this->whenLoaded('companies', fn() => CompanyResource::collection($this->getVisibleCompaniesForUser() ?? collect())),
            'cashBoxes' => $this->whenLoaded('cashBoxes', fn() => CashBoxResource::collection($this->cashBoxes ?? collect())),
            'branches' => $this->whenLoaded('branches', function () {
                if ($this->hasPermissionTo(perm_key('admin.company')) || $this->hasPermissionTo(perm_key('admin.super'))) {
                    $branches = \Modules\Companies\Models\Branch::where('company_id', $this->active_company_id)->get();
                } else {
                    // Ø¥Ø¶Ø§ÙØ© Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠ Ø¥Ø°Ø§ Ù„Ù… ÙŠÙƒÙ† Ø¶Ù…Ù† Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„Ù…Ø­Ù…Ù„Ø©
                    $branches = $this->branches;
                    if ($this->branch_id && !$branches->contains('id', $this->branch_id)) {
                        $defaultBranch = \Modules\Companies\Models\Branch::find($this->branch_id);
                        if ($defaultBranch) {
                            $branches->push($defaultBranch);
                        }
                    }
                }
                return \Modules\Companies\Transformers\BranchResource::collection($branches);
            }),

            // Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª ÙˆØ§Ù„Ø§Ø¯ÙˆØ§Ø±
            'roles' => $this->getRolesWithPermissions(),
            'permissions' => $this->resource->hasPermissionTo(perm_key('admin.super'))
                ? \Spatie\Permission\Models\Permission::all()->pluck('name')
                : $this->getAllPermissions()->pluck('name'),
            'direct_permissions' => $this->resource->hasPermissionTo(perm_key('admin.super'))
                ? collect([perm_key('admin.super')])
                : $this->getDirectPermissions()->pluck('name'),

            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
            'subscription' => $this->active_company_id ? \App\Services\SaaS\LimitResolver::getSubscriptionUsageMatrix($this->active_company_id) : null,
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
