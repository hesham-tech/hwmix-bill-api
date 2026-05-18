<?php

namespace Modules\Inventory\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Company;
use App\Models\Image;
use App\Models\DigitalProductDelivery;

/**
 * موديل المنتج (Product) - موديول المخازن
 */
class Product extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity, HasImages;

    protected static function newFactory()
    {
        return \Database\Factories\ProductFactory::new();
    }

    protected $fillable = [
        'name',
        'product_type',
        'require_stock',
        'is_downloadable',
        'download_url',
        'download_limit',
        'license_keys',
        'available_keys_count',
        'validity_days',
        'expires_at',
        'delivery_instructions',
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
        'created_by',
        'sales_count',
        'is_active_in_store',
        'is_active_in_sales'
    ];

    // Product Type Constants
    const TYPE_PHYSICAL = 'physical';
    const TYPE_DIGITAL = 'digital';
    const TYPE_SERVICE = 'service';
    const TYPE_SUBSCRIPTION = 'subscription';

    protected $casts = [
        'product_type' => 'string',
        'require_stock' => 'boolean',
        'is_downloadable' => 'boolean',
        'download_limit' => 'integer',
        'license_keys' => 'array',
        'available_keys_count' => 'integer',
        'validity_days' => 'integer',
        'expires_at' => 'datetime',
        'active' => 'boolean',
        'featured' => 'boolean',
        'returnable' => 'boolean',
        'published_at' => 'datetime',
        'sales_count' => 'integer',
        'is_active_in_store' => 'boolean',
        'is_active_in_sales' => 'boolean',
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

    public function scopeInStore($query)
    {
        return $query->where('is_active_in_store', true)->where('active', true);
    }

    public function scopeInSales($query)
    {
        return $query->where('is_active_in_sales', true)->where('active', true);
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

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public static function generateSlug($name)
    {
        $slug = preg_replace('/[^\p{Arabic}a-z0-9\s-]/u', '', strtolower($name));
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);

        if (empty($slug)) {
            $slug = 'product';
        }

        $originalSlug = $slug;
        $i = 1;
        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function logLabel()
    {
        return "المنتج ({$this->name})";
    }
}
