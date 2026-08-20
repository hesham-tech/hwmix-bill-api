<?php

namespace App\Http\Resources\CompanyUser;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserResource extends JsonResource
{
    /**
     * ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯ Ø¥Ù„Ù‰ Ù…ØµÙÙˆÙØ©.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * Ø´Ø¹Ø§Ø± Ø§Ù„Ø´Ø±ÙƒØ©
         */
        $companyLogoUrl = $this->whenLoaded('company', function () {
            return $this->company->logo?->url;
        });

        /**
         * ØµÙˆØ±Ø© Ø§Ù„Ø£ÙØ§ØªØ§Ø±
         */
        $avatarUrl = $this->whenLoaded('user', function () {
            return collect($this->user->images ?? [])
                ->where('type', 'avatar')
                ->first()?->url;
        });

        // Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©
        $defaultCashBox = $this->whenLoaded('user', function () {
            return $this->user->getDefaultCashBoxForCompany($this->company_id, $this->branch_id ?? $this->user->branch_id);
        });

        /**
         * ÙƒÙ„ Ø§Ù„Ø®Ø²Ù† Ø§Ù„Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©
         */
        $companyCashBoxes = $this->whenLoaded('user', function () {
            if (!$this->user || !$this->user->relationLoaded('cashBoxes')) {
                return collect();
            }

            return collect($this->user->cashBoxes ?? [])
                ->where('company_id', $this->company_id);
        });

        return [
            // Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ù„Ù Ø§Ù„Ø¹Ø¶Ùˆ (Ø³ÙŠØ§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ©)
            'id' => $this->user_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'balance' => $this->active_branch_balance,
            'active_branch_balance' => $this->active_branch_balance,
            'custody_balance' => $this->custody_balance,
            'total_branches_balance' => $this->total_branches_balance,
            'position' => $this->position,
            'status' => $this->status,
            'customer_type' => $this->customer_type,
            'avatar_url' => $avatarUrl,

            // Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©
            'cash_box_id' => $this->getDefaultCashBoxAttribute()?->id,
            'company_id' => $this->company_id,
            'company_logo' => $companyLogoUrl,
            'last_login_at' => $this->last_login_at,
            'roles' => $this->whenLoaded('user', fn() => $this->user->roles->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),
            'companies' => $this->whenLoaded('user', fn() => CompanyResource::collection($this->user->companies ?? collect())),
            'branches' => $this->whenLoaded('user', function () {
                if (!$this->user || !$this->user->relationLoaded('branches')) {
                    return collect();
                }
                return \Modules\Companies\Transformers\BranchResource::collection($this->user->branches);
            }),
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),
        ];
    }
}
