<?php

namespace Modules\Legal\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * محول سجلات قبول موافقة المستندات القانونية إلى مصفوفة JSON موحدة.
 */
class LegalDocumentAcceptanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user ? ($this->user->name ?? $this->user->nickname) : 'مستخدم محذوف',
            'user_email' => $this->user ? $this->user->email : null,
            'legal_document_version_id' => $this->legal_document_version_id,
            'accepted_at' => $this->accepted_at,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'company_id' => $this->company_id,
            'version' => new LegalDocumentVersionResource($this->whenLoaded('version')),
        ];
    }
}
