<?php

namespace Modules\Legal\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * محول بيانات إصدارات المستندات القانونية إلى مصفوفة JSON موحدة.
 */
class LegalDocumentVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legal_document_id' => $this->legal_document_id,
            'version' => $this->version,
            'title' => $this->title,
            'content' => $this->content,
            'summary' => $this->summary,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'document_key' => $this->relationLoaded('document') ? $this->document->key : null,
        ];
    }
}
