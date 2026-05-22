<?php

namespace Modules\Guidance\Transformers;

/**
 * كلاس تحويل بيانات تقدم الإرشادات للمستخدم إلى تنسيق JSON الموحد للـ API.
 */

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuidanceProgressResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'skipped' => (bool) $this->skipped,
        ];
    }
}

