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

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'اسم المنتج',
            'الباركود / SKU',
            'التصنيف',
            'العلامة التجارية',
            'النوع',
            'سعر البيع',
            'سعر التكلفة',
            'المخزون المتوفر',
            'الحالة',
            'تاريخ الإضافة'
        ];
    }

    public function map($product): array
    {
        // Get prices from the first variant if available
        $variant = $product->variants->first();

        return [
            $product->id,
            $product->name,
            $variant?->sku ?? '',
            $product->category?->name ?? 'بدون تصنيف',
            $product->brand?->name ?? 'بدون ماركة',
            $this->translateType($product->product_type),
            $variant?->retail_price ?? 0,
            $variant?->purchase_price ?? 0,
            $product->total_available_quantity ?? 0,
            $product->active ? 'نشط' : 'مؤرشف',
            $product->created_at->format('Y-m-d H:i'),
        ];
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
