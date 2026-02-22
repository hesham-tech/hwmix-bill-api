<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;
    protected $user;

    public function __construct($query, $user = null)
    {
        $this->query = $query;
        $this->user = $user ?? auth()->user();
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        $headers = [
            'ID',
            'اسم المنتج',
            'الباركود / SKU',
            'التصنيف',
            'العلامة التجارية',
            'النوع',
            'سعر البيع',
        ];

        if ($this->hasPurchasePricePermission()) {
            $headers[] = 'سعر التكلفة';
        }

        return array_merge($headers, [
            'المخزون المتوفر',
            'الحالة',
            'تاريخ الإضافة'
        ]);
    }

    public function map($product): array
    {
        // Get prices from the first variant if available
        $variant = $product->variants->first();

        $data = [
            $product->id,
            $product->name,
            $variant?->sku ?? '',
            $product->category?->name ?? 'بدون تصنيف',
            $product->brand?->name ?? 'بدون ماركة',
            $this->translateType($product->product_type),
            $variant?->retail_price ?? 0,
        ];

        if ($this->hasPurchasePricePermission()) {
            $data[] = $variant?->purchase_price ?? 0;
        }

        return array_merge($data, [
            $product->total_available_quantity ?? 0,
            $product->active ? 'نشط' : 'مؤرشف',
            $product->created_at->format('Y-m-d H:i'),
        ]);
    }

    protected function hasPurchasePricePermission(): bool
    {
        if (!$this->user)
            return false;

        return $this->user->hasAnyPermission([
            perm_key('admin.super'),
            perm_key('admin.company'),
            perm_key('products.view_purchase_price')
        ]);
    }

    protected function translateType($type): string
    {
        return match ($type) {
            'physical' => 'مادي',
            'digital' => 'رقمي',
            'service' => 'خدمة',
            'subscription' => 'اشتراك',
            default => $type,
        };
    }
}
