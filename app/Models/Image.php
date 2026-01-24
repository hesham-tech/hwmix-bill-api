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
        'is_primary',
        'file_name',
        'mime_type',
        'size'
    ];

    protected $casts = [
        'is_temp' => 'boolean',
        'is_primary' => 'boolean',
        'imageable_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Get the full URL for the image.
     */
    public function getUrlAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        // If it's a full URL, strip the host to make it relative (for proxy support)
        if (str_starts_with($value, 'http')) {
            return parse_url($value, PHP_URL_PATH);
        }

        // Ensure it starts with a leading slash
        return '/' . ltrim($value, '/');
    }

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
