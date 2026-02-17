<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ProductImportController extends Controller
{
    /**
     * Import products from Excel data with mapping
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mapping' => 'required|array',
                'data' => 'required|array',
            ]);

            $mapping = $request->input('mapping');
            $rows = $request->input('data');
            $companyId = Auth::user()->company_id;
            $userId = Auth::id();

            $count = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                try {
                    $productData = $this->mapRowToProduct($row, $mapping);

                    if (empty($productData['name'])) {
                        continue;
                    }

                    // 1. Handle Category
                    if (!empty($productData['category_name'])) {
                        $productData['category_id'] = $this->getOrCreateCategory($productData['category_name'], $companyId);
                    }

                    // 2. Handle Brand
                    if (!empty($productData['brand_name'])) {
                        $productData['brand_id'] = $this->getOrCreateBrand($productData['brand_name'], $companyId);
                    }

                    // 3. Create Product
                    $product = Product::create([
                        'company_id' => $companyId,
                        'created_by' => $userId,
                        'name' => $productData['name'],
                        'name_en' => $productData['name_en'] ?? null,
                        'desc' => $productData['desc'] ?? null,
                        'category_id' => $productData['category_id'] ?? null,
                        'brand_id' => $productData['brand_id'] ?? null,
                        'product_type' => 'physical',
                        'active' => true,
                    ]);

                    // 4. Create Default Variant & Stock if prices/quantities are provided
                    if (isset($productData['sale_price']) || isset($productData['cost_price']) || isset($productData['opening_stock'])) {
                        $variant = $product->variants()->create([
                            'company_id' => $companyId,
                            'sku' => $productData['sku'] ?? null,
                            'retail_price' => $productData['sale_price'] ?? 0,
                            'purchase_price' => $productData['cost_price'] ?? 0,
                        ]);

                        if (!empty($productData['opening_stock'])) {
                            // Link to default or first warehouse if not specified
                            $warehouseId = $productData['warehouse_id'] ?? \App\Models\Warehouse::where('company_id', $companyId)
                                ->orderBy('is_default', 'desc')
                                ->value('id');

                            if (!$warehouseId) {
                                // Create default warehouse if none exist
                                $newWarehouse = \App\Models\Warehouse::create([
                                    'company_id' => $companyId,
                                    'name' => 'المخزن الرئيسي',
                                    'is_default' => true,
                                    'created_by' => $userId,
                                ]);
                                $warehouseId = $newWarehouse->id;
                            }

                            $variant->stocks()->create([
                                'company_id' => $companyId,
                                'warehouse_id' => $warehouseId,
                                'quantity' => $productData['opening_stock'],
                            ]);
                        }
                    }

                    $count++;
                } catch (Throwable $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return api_success([
                'count' => $count,
                'errors' => $errors,
            ], "تم استيراد $count منتج بنجاح.");

        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }

    private function mapRowToProduct(array $row, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $excelCol => $systemField) {
            if (isset($row[$excelCol])) {
                if ($systemField === 'category') {
                    $mapped['category_name'] = $row[$excelCol];
                } elseif ($systemField === 'brand') {
                    $mapped['brand_name'] = $row[$excelCol];
                } else {
                    $mapped[$systemField] = $row[$excelCol];
                }
            }
        }
        return $mapped;
    }

    private function getOrCreateCategory(string $name, ?string $companyId): ?string
    {
        $category = Category::where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        if (!$category) {
            // Check global
            $category = Category::whereNull('company_id')
                ->where('name', $name)
                ->first();
        }

        if (!$category) {
            $category = Category::create([
                'company_id' => $companyId,
                'name' => $name,
            ]);
        }

        return $category->id;
    }

    private function getOrCreateBrand(string $name, ?string $companyId): ?string
    {
        $brand = Brand::where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        if (!$brand) {
            // Check global
            $brand = Brand::whereNull('company_id')
                ->where('name', $name)
                ->first();
        }

        if (!$brand) {
            $brand = Brand::create([
                'company_id' => $companyId,
                'name' => $name,
            ]);
        }

        return $brand->id;
    }
}
