<?php

namespace App\Http\Resources\CompanyUser;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserBasicResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // الحصول على صورة الأفاتار للمستخدم من علاقة المستخدم
        $avatarUrl = $this->whenLoaded('user', function () {
            return $this->user->images->where('type', 'avatar')->first()?->url;
        });

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

        return [
            // بيانات ملف العضو (سياق الشركة)
            'id' => $this->user_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'position' => $this->position,
            'status' => $this->status,
            'customer_type' => $this->customer_type,
            'avatar_url' => $avatarUrl,

            // معلومات إضافية
            'id_company_user' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),
            'cash_box_id' => $defaultCashBox instanceof \Illuminate\Http\Resources\MissingValue ? null : $defaultCashBox?->id,
            'roles' => $this->whenLoaded('user', fn() => $this->user->roles->pluck('name')),
            'direct_permissions' => $this->whenLoaded('user', fn() => $this->user->getDirectPermissions()->pluck('name')),
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}
