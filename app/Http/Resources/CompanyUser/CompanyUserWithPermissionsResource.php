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
            // بيانات المستخدم الأساسية
            'id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn() => $this->user->username),
            'email' => $this->whenLoaded('user', fn() => $this->user->email),
            'phone' => $this->whenLoaded('user', fn() => $this->user->phone),
            'last_login_at' => $this->whenLoaded('user', fn() => $this->user->last_login_at),
            'email_verified_at' => $this->whenLoaded('user', fn() => $this->user->email_verified_at),
            'created_by' => $this->whenLoaded('user', fn() => $this->user->created_by),

            // بيانات من جدول company_user
            'nickname' => $this->nickname_in_company,
            'full_name' => $this->full_name_in_company,
            'balance' => $this->balance_in_company,
            'position' => $this->position_in_company,
            'status' => $this->status,
            'customer_type' => $this->customer_type_in_company,

            // الخزنة الافتراضية
            'cash_box_id' => $defaultCashBox instanceof \Illuminate\Http\Resources\MissingValue ? null : $defaultCashBox?->id,
            'cashBoxDefault' => $defaultCashBox ? new CashBoxResource($defaultCashBox) : null,

            // الشركة الحالية
            'company_id' => $this->company_id,
            'company_logo' => $companyLogoUrl,

            // الأدوار والصلاحيات
            'roles' => $this->whenLoaded('user', fn() => $this->user->getRolesWithPermissions()),
            'permissions' => $this->whenLoaded('user', fn() => $this->user->getAllPermissions()->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),

            // الصورة
            'avatar_url' => $avatarUrl,

            // الشركات المرتبطة بالمستخدم
            'companies' => $this->whenLoaded(
                'user',
                fn() =>
                CompanyResource::collection($this->user->getVisibleCompaniesForUser() ?? collect()),
                collect()
            ),

            // الخزن التابعة للشركة
            'cashBoxes' => CashBoxResource::collection($companyCashBoxes ?? collect()),

            // أوقات الإنشاء والتحديث
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),

            // الإعدادات
            'settings' => $this->whenLoaded('user', fn() => $this->user->settings ?? null),
        ];
    }
}
