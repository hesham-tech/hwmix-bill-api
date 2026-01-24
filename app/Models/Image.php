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

        // Determine the relative path
        $path = $value;

        // If it's a full URL, check if it's pointing to our storage
        if (str_starts_with($value, 'http')) {
            // Check if it contains '/storage/' which is our default public storage path
            if (str_contains($value, '/storage/')) {
                $path = explode('/storage/', $value)[1];
            } else {
                // If it's an external URL (like S3 or external site), return it as is
                return $value;
            }
        }

        // Clean up the path (remove leading slashes and 'storage/' prefix if present)
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // Return full URL using our CORS-safe media serve route
        return route('media.serve', ['path' => $path]);
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
