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
        $companyId = $this->active_company_id ?? $this->company_id;
        $receivable = $this->getFinancialBalance($companyId, 'receivable');
        $payable    = $this->getFinancialBalance($companyId, 'payable');

        // ØªØ­Ø¯ÙŠØ¯ Ø­Ù‚Ù„ balance Ø§Ù„ØµØ­ÙŠØ­ Ø­Ø³Ø¨ Ù†ÙˆØ¹ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
        // Ø§Ù„Ù…ÙˆØ¸Ù â†’ Ø®Ø²Ù†Ø© Ø§Ù„Ø¹Ù‡Ø¯Ø© | Ø§Ù„Ø¹Ù…ÙŠÙ„ â†’ Ø°Ù…ØªÙ‡ Ø§Ù„Ù…Ø¯ÙŠÙ†Ø© | Ø§Ù„Ù…ÙˆØ±Ø¯ â†’ Ù…Ø³ØªØ­Ù‚Ø§ØªÙ‡
        $relationTypes = $this->getRelationTypesForCompany($companyId);
        $isEmployee = $this->hasCapability('is_internal', $companyId);
        $legacyBalance = $isEmployee ? $this->active_branch_balance : ($receivable > 0 ? $receivable : -$payable);

        $capabilities = $this->getCapabilitiesForCompany($companyId);

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'nickname'              => $this->nickname,
            'full_name'             => $this->full_name,
            'username'              => $this->username,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            // @deprecated â€” Ø§Ø³ØªØ®Ø¯Ù… receivable_balance / payable_balance / cashbox_balance
            'balance'               => $legacyBalance,
            'active_branch_balance' => $this->active_branch_balance,
            'custody_balance' => $this->custody_balance,
            'total_branches_balance'=> $this->total_branches_balance,
            'cashbox_balance'       => $this->active_branch_balance,
            'receivable_balance'    => $receivable,
            'payable_balance'       => $payable,
            'financial_balance'     => $receivable - $payable,
            'customer_type'         => $this->customer_type,
            'relation_types'        => $relationTypes,
            'capabilities'          => $capabilities,
            'starting_balances'     => [
                'receivable' => $receivable,
                'payable'    => $payable,
            ],
            'position'              => $this->position,
            'status'                => $this->status,
            'avatar_url'            => $this->avatar_url,
            'active_company_id'     => $this->active_company_id,
            'company_name'          => $this->whenLoaded('company', fn() => $this->company->name),
            'company_logo'          => $this->whenLoaded('company', fn() => $this->company->logo?->url),
            'cash_box_id'           => $this->getDefaultCashBoxForCompany()?->id,
            'last_login_at'         => $this->last_login_at,
            'email_verified_at'     => $this->email_verified_at,
            'created_by'            => $this->created_by,
            'roles'                 => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'direct_permissions'    => $this->whenLoaded('permissions', fn() => $this->getDirectPermissions()->pluck('name')),
            'companies'             => $this->whenLoaded('companies', fn() => CompanyResource::collection($this->getVisibleCompaniesForUser() ?? collect())),
            'branches'              => $this->whenLoaded('branches', fn() => \Modules\Companies\Transformers\BranchResource::collection($this->branches)),
            'created_at'            => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at'            => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }

    protected function getVisibleCompaniesForUser()
    {
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return Company::all();
        }
        return $this->companies ?? collect();
    }
}
