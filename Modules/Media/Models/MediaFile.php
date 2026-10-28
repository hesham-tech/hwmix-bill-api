<?php

namespace Modules\Media\Models;

//   موديل ملفات الوسائط لتسجيل تفاصيل وإدارة الصور والمستندات المرفوعة لكل شركة.

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use HasFactory, SoftDeletes, Blameable, LogsActivity, Scopes, FilterableByCompany;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function ($media) {
            if (\Illuminate\Support\Facades\Schema::hasTable('label_template_asset_references') &&
                \Illuminate\Support\Facades\DB::table('label_template_asset_references')->where('media_file_id', $media->id)->exists()) {
                abort(409, 'هذه الصورة مرتبطة بقالب ملصقات محفوظ.');
            }
        });
    }

    /**
     * ميثود للحصول على تفاصيل المسمى لأغراض السجلات.
     */
    public function logLabel()
    {
        return "ملف وسائط [{$this->original_name}] - النوع: {$this->mime_type}";
    }
}
