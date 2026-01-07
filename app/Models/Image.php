<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'images';
    protected $fillable = [
        'url',
        'type',
        'imageable_id',
        'imageable_type',
        'company_id',
        'created_by',
        'is_temp',
        'file_name',
        'mime_type',
        'size'
    ];

    protected $casts = [
        'is_temp' => 'boolean',
        'imageable_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function imageable()
    {
        return $this->morphTo();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
