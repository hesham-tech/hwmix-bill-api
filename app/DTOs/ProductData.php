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
