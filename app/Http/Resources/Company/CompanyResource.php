<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // تحميل العميل النقدي إذا كان متاحاً
        $cashCustomer = null;
        if ($this->default_cash_customer_id) {
            $cashCustomer = \App\Models\User::withoutGlobalScopes()
                ->select(['id', 'full_name', 'nickname', 'phone', 'email', 'active_company_id'])
                ->find($this->default_cash_customer_id);
        }

        return [
            'id' => $this->id,
            'owner_name' => $this->owner_name,
            'name' => $this->name,
            'field' => $this->field,
            'phone' => $this->phone,
            'address' => $this->address,
            'description' => $this->description,
            'email' => $this->email,
            'tax_number' => $this->tax_number,
            'website' => $this->website,
            'settings' => $this->settings,
            'print_settings' => $this->print_settings,
            'created_by' => $this->created_by,
            'logo' => $this->logo?->url,
            'default_cash_customer_id' => $this->default_cash_customer_id,
            'default_cash_customer' => $cashCustomer ? [
                'id' => $cashCustomer->id,
                'name' => $cashCustomer->full_name ?? 'عميل نقدي',
                'nickname' => $cashCustomer->nickname ?? 'عميل نقدي',
                'phone' => $cashCustomer->phone,
                'email' => $cashCustomer->email,
                'balance' => 0,
                'is_default_cash_customer' => true,
                'customer_type' => 'retail',
            ] : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
