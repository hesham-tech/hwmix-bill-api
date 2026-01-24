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
        $avatarImage = $this->images->where('type', 'avatar')->first();
        return [
            'id' => $this->id,
            'balance' => optional($this->cashBoxDefault)->balance ?? 0,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'customer_type' => $this->activeCompanyUser?->customer_type ?? $this->customer_type ?? 'retail',
            'cash_box_id' => optional($this->cashBoxDefault)->id,
            'avatar_url' => $this->images->where('type', 'avatar')->first()?->url,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}
