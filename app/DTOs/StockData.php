<?php

namespace App\DTOs;

class StockData
{
    public function __construct(
        public ?int $id = null,
        public int $quantity = 0,
        public int $min_quantity = 0,
        public ?float $cost = null,
        public ?string $batch = null,
        public ?string $expiry = null,
        public ?string $loc = null,
        public string $status = 'available',
        public ?int $warehouse_id = null,
        public ?int $company_id = null,
        public ?int $created_by = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            quantity: $data['quantity'] ?? 0,
            min_quantity: $data['min_quantity'] ?? 0,
            cost: isset($data['cost']) ? (float) $data['cost'] : null,
            batch: $data['batch'] ?? null,
            expiry: $data['expiry'] ?? null,
            loc: $data['loc'] ?? null,
            status: $data['status'] ?? 'available',
            warehouse_id: $data['warehouse_id'] ?? null,
            company_id: $data['company_id'] ?? null,
            created_by: $data['created_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'quantity' => $this->quantity,
            'min_quantity' => $this->min_quantity,
            'cost' => $this->cost,
            'batch' => $this->batch,
            'expiry' => $this->expiry,
            'loc' => $this->loc,
            'status' => $this->status,
            'warehouse_id' => $this->warehouse_id,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
        ], fn($value) => !is_null($value));
    }
}
