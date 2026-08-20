<?php

namespace App\Http\Resources\CompanyUser;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use App\Http\Resources\Company\CompanyResource;

class CompanyUserBasicResource extends JsonResource
{
    /**
     * ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯ Ø¥Ù„Ù‰ Ù…ØµÙÙˆÙØ©.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ ØµÙˆØ±Ø© Ø§Ù„Ø£ÙØ§ØªØ§Ø± Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…Ù† Ø¹Ù„Ø§Ù‚Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
        $avatarUrl = $this->whenLoaded('user', function () {
            return collect($this->user->images ?? [])->where('type', 'avatar')->first()?->url;
        });

        // Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©
        $defaultCashBox = $this->whenLoaded('user', function () {
            return $this->user->getDefaultCashBoxForCompany($this->company_id, $this->branch_id ?? $this->user->branch_id);
        });

        return [
            // Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ù„Ù Ø§Ù„Ø¹Ø¶Ùˆ (Ø³ÙŠØ§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ©)
            'id' => $this->user_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'balance' => $this->active_branch_balance,
            'active_branch_balance' => $this->active_branch_balance,
            'custody_balance' => $this->custody_balance,
            'total_branches_balance' => $this->total_branches_balance,
            'position' => $this->position,
            'status' => $this->status,
            'customer_type' => $this->customer_type,
            'avatar_url' => $avatarUrl,

            // Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©
            'id_company_user' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),
            'cash_box_id' => $defaultCashBox instanceof \Illuminate\Http\Resources\MissingValue ? null : $defaultCashBox?->id,
            'roles' => $this->whenLoaded('user', fn() => $this->user->roles->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),
            'companies' => $this->whenLoaded('user', fn() => CompanyResource::collection($this->user->companies ?? collect())),
            'branches' => $this->whenLoaded('user', function () {
                if (!$this->user || !$this->user->relationLoaded('branches')) {
                    return collect();
                }
                return \Modules\Companies\Transformers\BranchResource::collection($this->user->branches);
            }),
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}
