<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\HasImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 */
class ProductVariant extends Model
{
    use HasFactory, Blameable, Scopes, \App\Traits\LogsActivity, HasImages;

    /**
     * Get the primary image URL for the variant.
     * Fallback to product primary image if variant has no images.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('images')) {
            $url = $this->images->firstWhere('is_primary', true)?->url ?? $this->images->first()?->url;
            if ($url) {
                return $url;
            }
        }

        return $this->product?->primary_image_url;
    }

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "متغير منتج ({$this->product?->name} - {$this->sku})";
    }

    protected $fillable = [
        'barcode',
        'sku',
        'retail_price',
        'wholesale_price',
        'purchase_price',
        'profit_margin',
        'image',
        'weight',
        'dimensions',
        'tax',
        'discount',
        'min_quantity',
        'status',
        'product_id',
        'company_id',
        'created_by',
        'sales_count'
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'weight' => 'decimal:2',
        'dimensions' => 'array',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'sales_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'variant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // 🔁 المتغير ممكن يكون مرتبط ببنود فواتير
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'variant_id');
    }
    // 🔁 المتغير ممكن يكون مرتبط بقيم السمات


    public function attributes()
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($variant) {
            if (empty($variant->sku)) {
                $variant->sku = self::generateUniqueSKU();
            }
            if (empty($variant->barcode)) {
                $variant->barcode = self::generateUniqueBarcode();
            }
        });
    }

    private static function generateUniqueSKU()
    {
        do {
            $sku = 'SKU-' . strtoupper(Str::random(8));
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }

    private static function generateUniqueBarcode()
    {
        // الحصول على آخر قيمة للباركود من قاعدة البيانات (رقمية فقط)
        $lastBarcode = self::whereRaw("barcode REGEXP '^[0-9]+$'")
            ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
            ->first();

        // إذا لم يكن هناك باركودات سابقة، ابدأ من الرقم 1000000000
        $nextBarcode = $lastBarcode ? (int) $lastBarcode->barcode + 1 : 1000000000;

        // ملء الأصفار لتأكيد أن الباركود طويل بما يكفي (على سبيل المثال 10 خانات)
        $barcode = str_pad($nextBarcode, 10, '0', STR_PAD_LEFT);

        // التأكد من أن الباركود فريد
        while (self::where('barcode', $barcode)->exists()) {
            $nextBarcode++;
            $barcode = str_pad($nextBarcode, 10, '0', STR_PAD_LEFT);
        }

        return $barcode;
    }
}
