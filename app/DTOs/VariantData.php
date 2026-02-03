<?php

namespace App\DTOs;

class VariantData
{
    /**
     * @param StockData[] $stocks
     */
    public function __construct(
        public ?int $id = null,
        public ?string $barcode = null,
        public ?string $sku = null,
        public float $retail_price = 0,
        public float $wholesale_price = 0,
        public float $purchase_price = 0,
        public float $profit_margin = 0,
        public ?string $image = null,
        public float $weight = 0,
        public ?string $dimensions = null,
        public float $tax = 0,
        public float $discount = 0,
        public int $min_quantity = 0,
        public string $status = 'active',
        public array $attributes = [],
        public array $stocks = [],
        public ?int $company_id = null,
        public ?int $created_by = null,
        public array $image_ids = [],
        public ?int $primary_image_id = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            barcode: $data['barcode'] ?? null,
            sku: $data['sku'] ?? null,
            retail_price: (float) ($data['retail_price'] ?? 0),
            wholesale_price: (float) ($data['wholesale_price'] ?? 0),
            purchase_price: (float) ($data['purchase_price'] ?? 0),
            profit_margin: (float) ($data['profit_margin'] ?? 0),
            image: $data['image'] ?? null,
            weight: (float) ($data['weight'] ?? 0),
            dimensions: $data['dimensions'] ?? null,
            tax: (float) ($data['tax'] ?? 0),
            discount: (float) ($data['discount'] ?? 0),
            min_quantity: (int) ($data['min_quantity'] ?? 0),
            status: $data['status'] ?? 'active',
            attributes: $data['attributes'] ?? [],
            stocks: array_map(fn($stock) => StockData::fromArray($stock), $data['stocks'] ?? []),
            company_id: $data['company_id'] ?? null,
            created_by: $data['created_by'] ?? null,
            image_ids: $data['image_ids'] ?? [],
            primary_image_id: $data['primary_image_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'purchase_price' => $this->purchase_price,
            'profit_margin' => $this->profit_margin,
            'image' => $this->image,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'min_quantity' => $this->min_quantity,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
        ], fn($value) => !is_null($value));
    }
}
