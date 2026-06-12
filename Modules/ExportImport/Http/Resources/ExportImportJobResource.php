<?php

namespace Modules\ExportImport\Http\Resources;

//   مورد بيانات لتغليف وعرض تفاصيل مهام التصدير والاستيراد بشكل JSON موحد وآمن.

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExportImportJobResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'model_type' => $this->model_type,
            'status' => $this->status,
            'progress' => $this->progress,
            // توفير رابط التحميل الآمن فقط إذا كانت المهمة مكتملة ومسار الملف موجود
            'download_url' => ($this->status === 'completed' && $this->file_path)
                ? route('export-import.download', ['id' => $this->id])
                : null,
            'errors' => $this->errors,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
