<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\Store\Services\StoreCatalogFilters;
use PHPUnit\Framework\TestCase;

class StoreCatalogFiltersTest extends TestCase
{
    private function ids(array $filters): array
    {
        // An explicit in-memory connection, without Laravel, .env, providers or migrations.
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'));
        $connection->statement('CREATE TABLE products (id INTEGER, category_id INTEGER, brand_id INTEGER)');
        $connection->table('products')->insert([
            ['id' => 1, 'category_id' => 10, 'brand_id' => 20],
            ['id' => 2, 'category_id' => 11, 'brand_id' => 21],
            ['id' => 3, 'category_id' => 10, 'brand_id' => 22],
            ['id' => 4, 'category_id' => 12, 'brand_id' => 20],
        ]);
        $query = new Builder($connection->table('products'));
        StoreCatalogFilters::apply($query, $filters);
        return $query->getQuery()->orderBy('id')->pluck('id')->all();
    }

    public function test_multi_selection_is_or_within_a_group_and_and_between_groups(): void
    {
        $this->assertSame([1, 2, 3], $this->ids(['category_ids' => [10, 11]]));
        $this->assertSame([1, 2], $this->ids(['category_ids' => [10, 11], 'brand_ids' => [20, 21]]));
        $this->assertSame([], $this->ids(['category_ids' => [12], 'brand_ids' => [22]]));
    }

    public function test_legacy_scalar_ids_and_empty_selections_remain_compatible(): void
    {
        $this->assertSame([1, 3], $this->ids(['category_id' => 10]));
        $this->assertSame([1], $this->ids(['category_id' => 10, 'brand_id' => 20]));
        $this->assertSame([1, 2, 3, 4], $this->ids(['category_ids' => [], 'brand_ids' => []]));
        $this->assertSame([2], $this->ids(['category_id' => 10, 'category_ids' => [11]]));
    }

    public function test_validation_bounds_lists_and_rejects_malformed_or_duplicate_ids(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $rules = StoreCatalogFilters::rules();
        $this->assertTrue($factory->make(['category_ids' => ['10', '11'], 'brand_id' => '20'], $rules)->passes());
        foreach ([['category_ids' => '10,11'], ['brand_ids' => [20, 20]], ['category_ids' => [0]],
            ['brand_ids' => ['invalid']], ['category_ids' => range(1, 25)], ['brand_ids' => [null]],
            ['category_id' => [10, 11]]] as $invalid) {
            $this->assertTrue($factory->make($invalid, $rules)->fails());
        }
    }
}
