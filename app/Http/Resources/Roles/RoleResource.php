<?php

namespace App\Http\Resources\Roles;

use App\Http\Resources\Company\CompanyBasicResource;
use App\Http\Resources\User\UserBasicResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'guard_name' => $this->guard_name,
            'permissions_count' => $this->permissions_count ?? $this->permissions()->count(),
            'users_count' => $this->users_count ?? $this->users()->count(),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name');
            }),
            'company' => new CompanyBasicResource($this->whenLoaded('company')),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}
