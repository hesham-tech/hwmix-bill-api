<?php

namespace App\Http\Resources\CompanyUser;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserWithPermissionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
            return $this->user->images
                ->where('type', 'avatar')
                ->first()?->url;
        });

        /**
         * Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©
         */
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

            return $this->user->cashBoxes
                ->where('company_id', $this->company_id);
        });

        return [
            // Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
            'id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn() => $this->user->username),
            'email' => $this->whenLoaded('user', fn() => $this->user->email),
            'phone' => $this->whenLoaded('user', fn() => $this->user->phone),
            'last_login_at' => $this->whenLoaded('user', fn() => $this->user->last_login_at),
            'email_verified_at' => $this->whenLoaded('user', fn() => $this->user->email_verified_at),
            'created_by' => $this->whenLoaded('user', fn() => $this->user->created_by),

            // Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ù† Ø¬Ø¯ÙˆÙ„ company_user
            'nickname' => $this->nickname_in_company,
            'full_name' => $this->full_name_in_company,
            'balance' => $this->active_branch_balance,
            'active_branch_balance' => $this->active_branch_balance,
            'custody_balance' => $this->custody_balance,
            'total_branches_balance' => $this->total_branches_balance,
            'position' => $this->position_in_company,
            'status' => $this->status,
            'customer_type' => $this->customer_type_in_company,

            // Ø§Ù„Ø®Ø²Ù†Ø© Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©
            'cash_box_id' => $defaultCashBox instanceof \Illuminate\Http\Resources\MissingValue ? null : $defaultCashBox?->id,
            'cashBoxDefault' => $defaultCashBox ? new CashBoxResource($defaultCashBox) : null,

            // Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©
            'company_id' => $this->company_id,
            'company_logo' => $companyLogoUrl,

            // Ø§Ù„Ø£Ø¯ÙˆØ§Ø± ÙˆØ§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª
            'roles' => $this->whenLoaded('user', fn() => $this->user->getRolesWithPermissions()),
            'permissions' => $this->whenLoaded('user', fn() => $this->user->getAllPermissions()->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),

            // Ø§Ù„ØµÙˆØ±Ø©
            'avatar_url' => $avatarUrl,

            // Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
            'companies' => $this->whenLoaded(
                'user',
                fn() =>
                CompanyResource::collection($this->user->getVisibleCompaniesForUser() ?? collect()),
                collect()
            ),

            // Ø§Ù„Ø®Ø²Ù† Ø§Ù„ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ø´Ø±ÙƒØ©
            'cashBoxes' => CashBoxResource::collection($companyCashBoxes ?? collect()),

            // Ø£ÙˆÙ‚Ø§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ ÙˆØ§Ù„ØªØ­Ø¯ÙŠØ«
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),

            // Ø§Ù„Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª
            'settings' => $this->whenLoaded('user', fn() => $this->user->settings ?? null),
            'branches' => $this->whenLoaded('user', function () {
                if (!$this->user || !$this->user->relationLoaded('branches')) {
                    return collect();
                }
                return \Modules\Companies\Transformers\BranchResource::collection($this->user->branches);
            }),
        ];
    }
}
