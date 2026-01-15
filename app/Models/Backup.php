<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $table = 'backup_history';
    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
