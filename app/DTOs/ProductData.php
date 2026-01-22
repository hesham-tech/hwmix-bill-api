<?php

namespace App\DTOs;

class ProductData
{
    /**
     * @param VariantData[] $variants
     */
    public function __construct(
        public string $name,
        public int $category_id,
        public ?string $product_type = 'physical',
        public bool $require_stock = true,
        public bool $is_downloadable = false,
        public ?string $download_url = null,
        public ?int $download_limit = null,
        public ?array $license_keys = null,
        public int $available_keys_count = 0,
        public ?int $validity_days = null,
        public ?string $expires_at = null,
        public ?string $delivery_instructions = null,
        public ?int $brand_id = null,
        public array $image_ids = [],
        public ?int $primary_image_id = null,
        public ?bool $active = true,
        public bool $featured = false,
        public bool $returnable = true,
        public ?string $desc = null,
        public ?string $desc_long = null,
        public ?string $published_at = null,
        public ?int $company_id = null,
        public ?int $created_by = null,
        public array $variants = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            category_id: (int) $data['category_id'],
            product_type: $data['product_type'] ?? 'physical',
            require_stock: (bool) ($data['require_stock'] ?? true),
            is_downloadable: (bool) ($data['is_downloadable'] ?? false),
            download_url: $data['download_url'] ?? null,
            download_limit: isset($data['download_limit']) ? (int) $data['download_limit'] : null,
            license_keys: $data['license_keys'] ?? null,
            available_keys_count: (int) ($data['available_keys_count'] ?? 0),
            validity_days: isset($data['validity_days']) ? (int) $data['validity_days'] : null,
            expires_at: $data['expires_at'] ?? null,
            delivery_instructions: $data['delivery_instructions'] ?? null,
            brand_id: isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            image_ids: $data['image_ids'] ?? [],
            primary_image_id: $data['primary_image_id'] ?? null,
            active: (bool) ($data['active'] ?? true),
            featured: (bool) ($data['featured'] ?? false),
            returnable: (bool) ($data['returnable'] ?? true),
            desc: $data['desc'] ?? null,
            desc_long: $data['desc_long'] ?? null,
            published_at: $data['published_at'] ?? null,
            company_id: $data['company_id'] ?? null,
            created_by: $data['created_by'] ?? null,
            variants: array_map(fn($v) => VariantData::fromArray($v), $data['variants'] ?? []),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'category_id' => $this->category_id,
            'product_type' => $this->product_type,
            'require_stock' => $this->require_stock,
            'is_downloadable' => $this->is_downloadable,
            'download_url' => $this->download_url,
            'download_limit' => $this->download_limit,
            'license_keys' => $this->license_keys,
            'available_keys_count' => $this->available_keys_count,
            'validity_days' => $this->validity_days,
            'expires_at' => $this->expires_at,
            'delivery_instructions' => $this->delivery_instructions,
            'brand_id' => $this->brand_id,
            'image_ids' => $this->image_ids,
            'primary_image_id' => $this->primary_image_id,
            'active' => $this->active,
            'featured' => $this->featured,
            'returnable' => $this->returnable,
            'desc' => $this->desc,
            'desc_long' => $this->desc_long,
            'published_at' => $this->published_at,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
        ], fn($value) => !is_null($value));
    }
}
