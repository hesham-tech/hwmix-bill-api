<?php

namespace Modules\Store\Services;

use Illuminate\Database\Eloquent\Builder;

final class StoreCatalogFilters
{
    public static function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'category_ids' => 'nullable|array|max:24',
            'category_ids.*' => 'required|integer|min:1|distinct',
            'brand_ids' => 'nullable|array|max:24',
            'brand_ids.*' => 'required|integer|min:1|distinct',
        ];
    }

    public static function apply(Builder $query, array $filters): void
    {
        // OR within each list, AND between category and brand. Legacy IDs remain supported.
        foreach (['category', 'brand'] as $field) {
            if (!empty($filters[$field . '_ids'])) {
                $query->whereIn($field . '_id', $filters[$field . '_ids']);
            } elseif (!empty($filters[$field . '_id'])) {
                $query->where($field . '_id', $filters[$field . '_id']);
            }
        }
    }
}
