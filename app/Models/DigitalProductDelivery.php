<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class DigitalProductDelivery extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes;

    // Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_ACTIVATED = 'activated';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    // Delivery Type Constants
    const DELIVERY_LICENSE_KEY = 'license_key';
    const DELIVERY_DOWNLOAD_LINK = 'download_link';
    const DELIVERY_ACCOUNT_CREDENTIALS = 'account_credentials';
    const DELIVERY_CODE = 'code';
    const DELIVERY_OTHER = 'other';

    protected $fillable = [
        'invoice_item_id',
        'product_id',
        'user_id',
        'delivery_type',
        'delivery_data',
        'status',
        'delivered_at',
        'activated_at',
        'expires_at',
        'download_count',
        'last_downloaded_at',
        'activation_count',
        'last_activated_at',
        'notes',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_data' => 'array',
        'delivered_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'last_activated_at' => 'datetime',
    ];

    /**
     * Auto-set expiry date عند الإنشاء
     */
    protected static function booted()
    {
        static::creating(function ($delivery) {
            if ($delivery->product && $delivery->product->validity_days) {
                $delivery->expires_at = now()->addDays($delivery->product->validity_days);
            }
        });
    }

    // ==================== العلاقات ====================

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ==================== Methods ====================

    /**
     * تسليم المنتج الرقمي تلقائياً
     */
    public function deliver(): bool
    {
        try {
            if ($this->status !== self::STATUS_PENDING) {
                Log::warning("محاولة تسليم منتج رقمي تم تسليمه مسبقاً", ['delivery_id' => $this->id]);
                return false;
            }

            $this->delivery_data = $this->generateDeliveryData();

            if (empty($this->delivery_data)) {
                Log::error("فشل توليد بيانات التسليم", ['delivery_id' => $this->id, 'product_id' => $this->product_id]);
                return false;
            }

            $this->status = self::STATUS_DELIVERED;
            $this->delivered_at = now();

            $saved = $this->save();

            if ($saved) {
                Log::info("تم تسليم منتج رقمي بنجاح", [
                    'delivery_id' => $this->id,
                    'product_id' => $this->product_id,
                    'user_id' => $this->user_id,
                    'delivery_type' => $this->delivery_type,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error("خطأ أثناء تسليم منتج رقمي", [
                'delivery_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate delivery data based on product type
     */
    private function generateDeliveryData(): array
    {
        $product = $this->product;

        if (!$product) {
            return [];
        }

        // حسب نوع المنتج
        if ($product->license_keys && $product->available_keys_count > 0) {
            return $this->assignLicenseKey();
        }

        if ($product->is_downloadable && $product->download_url) {
            return $this->generateDownloadLink();
        }

        // إذا لم يكن هناك مفاتيح أو روابط، نعيد معلومات أساسية
        return [
            'type' => 'manual',
            'message' => $product->delivery_instructions ?? 'سيتم التواصل معك لتسليم المنتج',
            'product_name' => $product->name,
        ];
    }

    /**
     * تعيين مفتاح تفعيل من المخزون
     */
    private function assignLicenseKey(): array
    {
        $product = $this->product;
        $keys = $product->license_keys ?? [];

        if (empty($keys)) {
            return [];
        }

        // أخذ أول مفتاح متاح
        $assignedKey = array_shift($keys);

        // تحديث المنتج
        $product->license_keys = $keys;
        $product->available_keys_count = count($keys);
        $product->save();

        $this->delivery_type = self::DELIVERY_LICENSE_KEY;

        return [
            'license_key' => $assignedKey,
            'product_name' => $product->name,
            'instructions' => $product->delivery_instructions,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * إنشاء رابط تنزيل
     */
    private function generateDownloadLink(): array
    {
        $product = $this->product;

        $this->delivery_type = self::DELIVERY_DOWNLOAD_LINK;

        return [
            'download_url' => $product->download_url,
            'product_name' => $product->name,
            'download_limit' => $product->download_limit,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'instructions' => $product->delivery_instructions,
        ];
    }

    /**
     * تفعيل المنتج
     */
    public function activate(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return false;
        }

        $this->status = self::STATUS_ACTIVATED;
        $this->activated_at = now();
        $this->activation_count++;
        $this->last_activated_at = now();

        return $this->save();
    }

    /**
     * إلغاء التسليم (Revoke)
     */
    public function revoke(string $reason = null): bool
    {
        $this->status = self::STATUS_REVOKED;
        $this->notes = ($this->notes ?? '') . "\nتم الإلغاء: " . ($reason ?? 'بدون سبب') . " - " . now();

        return $this->save();
    }

    /**
     * تسجيل تنزيل
     */
    public function recordDownload(): bool
    {
        $this->download_count++;
        $this->last_downloaded_at = now();

        return $this->save();
    }
}
