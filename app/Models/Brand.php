<?php

namespace App\Models;

use App\Traits\LogsActivity;
use App\Traits\Blameable;
use App\Traits\HasImages;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\SmartSearch;

class Brand extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity, HasImages, SmartSearch;

    protected $fillable = ['name', 'slug', 'description', 'active', 'company_id', 'created_by', 'synonyms'];

    protected $casts = [
        'synonyms' => 'array',
        'active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    /**
     * Scope to search by name or synonyms.
     */
    public function scopeSearchBySynonym($query, $search)
    {
        $search = strtolower(trim($search));
        $normalized = $this->normalizeArabic($search);

        return $query->where(function ($q) use ($search, $normalized) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('slug', 'LIKE', "%{$search}%")
                ->orWhereJsonContains('synonyms', $search)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?", ["%{$normalized}%"]);
        });
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "الماركة ({$this->name})";
    }
}
