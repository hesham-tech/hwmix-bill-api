<?php

namespace App\Http\Resources\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'features' => $this->features,
            'max_users' => $this->max_users,
            'max_products' => $this->max_products,
            'max_invoices' => $this->max_invoices,
            'type' => $this->type,
            'icon' => $this->icon,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // علاقات
            'company' => $this->whenLoaded('company'),
            'creator' => $this->whenLoaded('creator'),
            'updater' => $this->whenLoaded('updater'),
            'subscriptions' => $this->whenLoaded('subscriptions'),
            'pricing_tiers' => $this->whenLoaded('pricingTiers'),
            // إحصائيات السوبر أدمن
            'active_companies_count' => $this->when(\Illuminate\Support\Facades\Auth::user()?->hasPermissionTo(perm_key('admin.super')), function() {
                return \App\Models\CompanySubscription::where('plan_id', $this->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
                    })
                    ->count();
            }),
            'active_users_count' => $this->when(\Illuminate\Support\Facades\Auth::user()?->hasPermissionTo(perm_key('admin.super')), function() {
                $activeCompanyIds = \App\Models\CompanySubscription::where('plan_id', $this->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
                    })
                    ->pluck('company_id')
                    ->toArray();
                    
                if (empty($activeCompanyIds)) return 0;
                
                return \App\Models\CompanyUser::whereIn('company_id', $activeCompanyIds)
                    ->distinct('user_id')
                    ->count();
            }),
        ];
    }
}
