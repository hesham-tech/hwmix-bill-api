<?php

namespace App\Http\Resources\CompanyUser;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * شعار الشركة
         */
        $companyLogoUrl = $this->whenLoaded('company', function () {
            return $this->company->logo?->url;
        });

        /**
         * صورة الأفاتار
         */
        $avatarUrl = $this->whenLoaded('user', function () {
            return $this->user->images
                ->where('type', 'avatar')
                ->first()?->url;
        });

        /**
         * الخزنة الافتراضية
         */
        $defaultCashBox = $this->whenLoaded('user', function () {
            if (!$this->user || !$this->user->relationLoaded('cashBoxes')) {
                return null;
            }

            return $this->user->cashBoxes
                ->where('is_default', true)
                ->where('company_id', $this->company_id)
                ->first();
        });

        /**
         * كل الخزن المرتبطة بالشركة الحالية
         */
        $companyCashBoxes = $this->whenLoaded('user', function () {
            if (!$this->user || !$this->user->relationLoaded('cashBoxes')) {
                return collect();
            }

            return $this->user->cashBoxes
                ->where('company_id', $this->company_id);
        });

        return [
            // بيانات ملف العضو (سياق الشركة)
            'id' => $this->user_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'balance' => $this->balance,
            'position' => $this->position,
            'status' => $this->status,
            'customer_type' => $this->customer_type,
            'avatar_url' => $avatarUrl,

            // معلومات إضافية
            'cash_box_id' => $this->getDefaultCashBoxAttribute()?->id,
            'company_id' => $this->company_id,
            'company_logo' => $companyLogoUrl,
            'last_login_at' => $this->last_login_at,
            'roles' => $this->whenLoaded('user', fn() => $this->user->roles->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),
        ];
    }
}
