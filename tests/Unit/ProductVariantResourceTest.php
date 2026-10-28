<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Inventory\Http\Resources\ProductVariantResource;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Tests\TestCase;

class ProductVariantResourceTest extends TestCase
{
    private function variant(): ProductVariant
    {
        $variant = new ProductVariant(['sku' => 'VARIANT-TEST']);
        $variant->setRelation('stocks', collect());
        $variant->setRelation('attributes', collect());
        $variant->setRelation('images', collect());
        $variant->setRelation('image', null);

        return $variant;
    }

    public function test_missing_parent_does_not_break_serialization(): void
    {
        $variant = $this->variant();
        $variant->product_id = 456;
        $variant->setRelation('product', null);

        $data = (new ProductVariantResource($variant))->resolve(Request::create('/'));

        $this->assertSame('VARIANT-TEST', $data['sku']);
        $this->assertArrayNotHasKey('product_name', $data);
        $this->assertSame(456, $data['product_id']);
        $this->assertFalse($data['product_available']);
    }

    public function test_available_parent_fields_are_preserved(): void
    {
        $variant = $this->variant();
        $product = new Product(['name' => 'Parent', 'slug' => 'parent', 'require_stock' => true]);
        $product->id = 123;
        $variant->product_id = 123;
        $product->setRelation('images', collect());
        $product->setRelation('image', null);
        $variant->setRelation('product', $product);

        $data = (new ProductVariantResource($variant))->resolve(Request::create('/'));

        $this->assertSame(123, $data['product_id']);
        $this->assertSame('Parent', $data['product_name']);
        $this->assertTrue($data['product_available']);
        $this->assertTrue($data['requires_stock']);
    }
}
