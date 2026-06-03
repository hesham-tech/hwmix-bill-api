<?php

namespace Modules\Legal\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * محول بيانات المستندات القانونية إلى مصفوفة JSON موحدة.
 */
class LegalDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'is_active' => $this->is_active,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'active_version' => new LegalDocumentVersionResource($this->whenLoaded('activeVersion')),
            'versions' => LegalDocumentVersionResource::collection($this->whenLoaded('versions')),
            'versions_count' => $this->versions_count ?? ($this->relationLoaded('versions') ? $this->versions->count() : null),
        ];
    }
}
