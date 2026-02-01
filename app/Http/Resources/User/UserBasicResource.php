<?php

namespace App\Http\Resources\User;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class UserBasicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'customer_type' => $this->customer_type,
            'position' => $this->position,
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'company_id' => $this->company_id,
            'cash_box_id' => $this->getDefaultCashBoxForCompany()?->id,
            'roles' => $this->roles->pluck('name'),
            'created_by' => $this->created_by,
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}
