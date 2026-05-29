<?php

namespace Modules\Media\Http\Resources;

// تعليق عربي: مورد بيانات لتغليف وعرض تفاصيل ملفات الوسائط وتوليد الروابط المباشرة للوصول إليها.

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaFileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'file_path' => $this->file_path,
            'file_url' => Storage::disk('public')->url($this->file_path), // الرابط المباشر للملف
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
