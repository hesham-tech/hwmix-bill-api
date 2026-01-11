<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'active',
        'featured',
        'returnable',
        'desc',
        'desc_long',
        'published_at',
        'category_id',
        'brand_id',
        'company_id',
        'created_by'
    ];

    protected $guarded = [];

    // Product Type Constants
    const TYPE_PHYSICAL = 'physical';
    const TYPE_DIGITAL = 'digital';
    const TYPE_SERVICE = 'service';
    const TYPE_SUBSCRIPTION = 'subscription';

    protected $casts = [
        'product_type' => 'string',
        'require_stock' => 'boolean',
        'is_downloadable' => 'boolean',
        'license_keys' => 'array',
        'available_keys_count' => 'integer',
        'validity_days' => 'integer',
        'expires_at' => 'datetime',
        'active' => 'boolean',
        'featured' => 'boolean',
        'returnable' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    // علاقة المنتج مع الـ variants
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function digitalDeliveries()
    {
        return $this->hasMany(DigitalProductDelivery::class);
    }

    // ==================== Scopes ====================

    public function scopePhysical($query)
    {
        return $query->where('product_type', self::TYPE_PHYSICAL);
    }

    public function scopeDigital($query)
    {
        return $query->where('product_type', self::TYPE_DIGITAL);
    }

    public function scopeService($query)
    {
        return $query->where('product_type', self::TYPE_SERVICE);
    }

    public function scopeSubscription($query)
    {
        return $query->where('product_type', self::TYPE_SUBSCRIPTION);
    }

    // ==================== Helper Methods ====================

    public function isPhysical(): bool
    {
        return $this->product_type === self::TYPE_PHYSICAL;
    }

    public function isDigital(): bool
    {
        return $this->product_type === self::TYPE_DIGITAL;
    }

    public function isService(): bool
    {
        return $this->product_type === self::TYPE_SERVICE;
    }

    public function isSubscription(): bool
    {
        return $this->product_type === self::TYPE_SUBSCRIPTION;
    }

    public function requiresStock(): bool
    {
        return $this->require_stock;
    }

    public function hasAvailableKeys(): bool
    {
        return $this->isDigital() && $this->available_keys_count > 0;
    }

    // ==================== العلاقات ====================

    // // علاقة المنتج مع الصور
    // public function images()
    // {
    //     return $this->hasMany(ProductImage::class);  // إذا كان هناك صور متعددة
    // }

    // دالة لتوليد الـ slug
    public static function generateSlug($name)
    {
        $slug = preg_replace('/[^\p{Arabic}a-z0-9\s-]/u', '', strtolower($name));
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);

        // في حال كانت النتيجة فارغة
        if (empty($slug)) {
            $slug = 'منتج';  // أو 'product' إن كنت تفضل الإنجليزي
        }

        $originalSlug = $slug;
        $i = 1;
        // التأكد من uniqueness
        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "المنتج ({$this->name})";
    }
}
