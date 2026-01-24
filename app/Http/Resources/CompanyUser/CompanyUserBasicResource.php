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
            // البيانات الأساسية للمستخدم (من جدول users)
            'id' => $this->user_id,
            'id_company_user' => $this->id,
            'username' => $this->user_username,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),

            // البيانات الخاصة بالشركة (من جدول company_user)
            'nickname' => $this->nickname,
            'balance' => $this->balance,
            'full_name' => $this->full_name,
            'customer_type' => $this->customer_type,
            'position' => $this->position_in_company,
            'status' => $this->status,

            // بيانات الخزنة الافتراضية
            'cash_box_id' => $defaultCashBox instanceof \Illuminate\Http\Resources\MissingValue ? null : $defaultCashBox?->id,
            'avatar_url' => $avatarUrl,

            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}
