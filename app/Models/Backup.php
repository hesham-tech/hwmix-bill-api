<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج سجلات النسخ الاحتياطي (Backup) لتتبع تاريخ ومزامنة النسخ الاحتياطية للنظام.
 */
class Backup extends Model
{
    use LogsActivity;
    protected $table = 'backup_history';
    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
