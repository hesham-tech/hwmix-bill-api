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
        // الحصول على شعار الشركة من علاقة الشركة
        $companyLogoUrl = $this->whenLoaded('company', function () {
            return $this->company->logo?->url ? asset($this->company->logo->url) : null;
        });

        // الحصول على صورة الأفاتار للمستخدم من علاقة المستخدم
        $avatarImage = $this->whenLoaded('user', function () {
            return $this->user->images->where('type', 'avatar')->first();
        });
        $avatarUrl = $avatarImage ? asset($avatarImage->url) : null;

        // الحصول على الخزنة الافتراضية للشركة الحالية
        $defaultCashBox = $this->whenLoaded('user', function () {
            if (!$this->user || !$this->user->relationLoaded('cashBoxes')) {
                return null;
            }

            return $this->user->cashBoxes
                ->where('is_default', true)
                ->where('company_id', $this->company_id)
                ->first();
        });

        // الحصول على صناديق النقد للشركة الحالية فقط
        $companyCashBoxes = $this->whenLoaded('user', function () {
            if (!$this->user || !$this->user->relationLoaded('cashBoxes')) {
                return collect();
            }

            return $this->user->cashBoxes
                ->where('company_id', $this->company_id);
        });

        return [
            // البيانات الأساسية للمستخدم (من جدول users)
            'id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn() => $this->user->username),
            'email' => $this->whenLoaded('user', fn() => $this->user->email),
            'phone' => $this->whenLoaded('user', fn() => $this->user->phone),
            'last_login_at' => $this->whenLoaded('user', fn() => $this->user->last_login_at),
            'email_verified_at' => $this->whenLoaded('user', fn() => $this->user->email_verified_at),
            'created_by' => $this->whenLoaded('user', fn() => $this->user->created_by),

            // البيانات الخاصة بالشركة (من جدول company_user)
            'nickname' => $this->nickname_in_company,
            'full_name' => $this->full_name_in_company,
            'balance' => $this->balance_in_company,
            'position' => $this->position_in_company,
            'status' => $this->status,
            'customer_type' => $this->customer_type_in_company,

            // بيانات الخزنة الافتراضية
            'cash_box_id' => $defaultCashBox?->id,
            'cashBoxDefault' => $defaultCashBox ? new CashBoxResource($defaultCashBox) : null,

            // بيانات الشركة النشطة
            'company_id' => $this->company_id,
            'company_logo' => $companyLogoUrl,

            // علاقات أخرى
            'roles' => $this->whenLoaded('user', fn() => $this->user->getRolesWithPermissions()),
            'avatar_url' => $avatarUrl,
            'companies' => $this->whenLoaded('user', fn() => CompanyResource::collection($this->user->getVisibleCompaniesForUser())),
            'cashBoxes' => $companyCashBoxes ? CashBoxResource::collection($companyCashBoxes) : [],
            'permissions' => $this->whenLoaded('user', fn() => $this->user->getAllPermissions()->pluck('name')),

            // أوقات الإنشاء والتحديث
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,

            // حقل settings
            'settings' => $this->whenLoaded('user', fn() => $this->user->settings ?? null),
        ];
    }
}
