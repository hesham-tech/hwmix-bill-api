<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SmartSearch
{
    /**
     * Scope to perform a smart search on specified columns.
     * 
     * @param Builder $query
     * @param string $searchTerm
     * @param array $columns
     * @param array $relationColumns [relation => [columns]]
     * @return Builder
     */
    public function scopeSmartSearch(Builder $query, $searchTerm, array $columns = [], array $relationColumns = [])
    {
        if (empty($searchTerm)) {
            return $query;
        }

        $normalized = $this->normalizeArabic($searchTerm);

        return $query->where(function ($q) use ($searchTerm, $normalized, $columns, $relationColumns) {
            // Search in direct columns
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', "%{$searchTerm}%")
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE({$column}, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?", ["%{$normalized}%"]);
            }

            // Search in related columns
            foreach ($relationColumns as $relation => $cols) {
                $q->orWhereHas($relation, function ($relQuery) use ($searchTerm, $normalized, $cols) {
                    $relQuery->where(function ($subQ) use ($searchTerm, $normalized, $cols) {
                        foreach ($cols as $col) {
                            $subQ->orWhere($col, 'LIKE', "%{$searchTerm}%")
                                ->orWhereRaw("REPLACE(REPLACE(REPLACE({$col}, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?", ["%{$normalized}%"]);
                        }
                    });
                });
            }
        });
    }

    /**
     * Normalize Arabic text for better matching.
     */
    public function normalizeArabic($text)
    {
        $text = trim($text);
        if (empty($text))
            return '';

        $search = ['أ', 'إ', 'آ', 'ة', 'ى', 'ـ'];
        $replace = ['ا', 'ا', 'ا', 'ه', 'ي', ''];

        return str_replace($search, $replace, $text);
    }

    /**
     * Refine a collection of items using similarity text matching.
     * 
     * @param \Illuminate\Support\Collection $items
     * @param string $searchTerm
     * @param array $fields
     * @param int $threshold Match percentage (e.g. 75)
     * @return \Illuminate\Support\Collection
     */
    public function refineSimilarity($items, $searchTerm, array $fields, int $threshold = 80)
    {
        if (empty($searchTerm) || $items->isEmpty()) {
            return $items;
        }

        $normalizedSearch = $this->normalizeArabic($searchTerm);

        return $items->map(function ($item) use ($searchTerm, $normalizedSearch, $fields) {
            $maxPercent = 0;

            foreach ($fields as $field) {
                // Get value (supports nested relations like 'customer.full_name')
                $value = data_get($item, $field);
                if (!$value)
                    continue;

                $normalizedValue = $this->normalizeArabic($value);

                // Strategy 1: Exact word match within the text
                if (str_contains($normalizedValue, $normalizedSearch)) {
                    $maxPercent = 100;
                    break;
                }

                // Strategy 2: Break words and compare similarity
                $words = preg_split('/\s+/', $normalizedValue);
                foreach ($words as $word) {
                    similar_text($normalizedSearch, $word, $percent);
                    $maxPercent = max($maxPercent, $percent);
                    if ($maxPercent >= 100)
                        break;
                }

                if ($maxPercent >= 100)
                    break;
            }

            $item->similarity_score = $maxPercent;
            return $item;
        })
            ->filter(fn($item) => $item->similarity_score >= $threshold)
            ->sortByDesc('similarity_score')
            ->values();
    }
}
