<?php

namespace App\Http\Controllers\Reports;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReportController extends BaseReportController
{
    /**
     * @group 05. التقارير والتحليلات
     * 
     * حركة المخزون
     * 
     * تقرير مفصل عن عمليات الوارد والمنصرف لكل منتج ومستودع.
     * 
     * @queryParam product_id integer فلترة حسب المنتج.
     * @queryParam date_from date تاريخ البداية.
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        $query = Stock::query()
            ->with(['product', 'warehouse', 'variant'])
            ->select([
                'product_id',
                'variant_id',
                'warehouse_id',
                DB::raw('SUM(CASE WHEN type = "in" THEN quantity ELSE 0 END) as total_in'),
                DB::raw('SUM(CASE WHEN type = "out" THEN quantity ELSE 0 END) as total_out'),
                DB::raw('SUM(CASE WHEN status = "available" THEN quantity ELSE 0 END) as current_stock'),
            ])
            ->groupBy('product_id', 'variant_id', 'warehouse_id');

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $report = $query->paginate($filters['per_page'] ?? 50);

        $summary = [
            'total_products' => $query->distinct('product_id')->count('product_id'),
            'total_in' => Stock::where('type', 'in')->sum('quantity'),
            'total_out' => Stock::where('type', 'out')->sum('quantity'),
            'current_stock' => Stock::where('status', 'available')->sum('quantity'),
        ];

        return response()->json([
            'report' => $report,
            'summary' => $summary,
            'filters' => $filters,
        ]);
    }

    /**
     * @group 05. التقارير والتحليلات
     * 
     * تقييم المخزون
     * 
     * حساب القيمة المالية للمخزون الحالي بناءً على سعر التكلفة وسعر البيع.
     */
    public function valuation(Request $request)
    {
        $filters = $this->validateFilters($request);

        $valuation = DB::table('stocks')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.status', 'available')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(stocks.quantity) as total_quantity'),
                DB::raw('AVG(product_variants.cost_price) as avg_cost'),
                DB::raw('SUM(stocks.quantity * product_variants.cost_price) as total_cost_value'),
                DB::raw('SUM(stocks.quantity * product_variants.price) as total_sale_value'),
            ])
            ->groupBy('products.id', 'products.name')
            ->get();

        $summary = [
            'total_cost_value' => round($valuation->sum('total_cost_value'), 2),
            'total_sale_value' => round($valuation->sum('total_sale_value'), 2),
            'potential_profit' => round($valuation->sum('total_sale_value') - $valuation->sum('total_cost_value'), 2),
        ];

        return response()->json([
            'valuation' => $valuation,
            'summary' => $summary,
        ]);
    }

    /**
     * @group 05. التقارير والتحليلات
     * 
     * تنبيهات نقص المخزون
     * 
     * عرض المنتجات التي وصلت كميتها إلى حد أقل من القيمة المحددة.
     * 
     * @queryParam threshold integer الحد الأدنى للكمية. Example: 10
     */
    public function lowStock(Request $request)
    {
        $threshold = $request->input('threshold', 10);

        $lowStock = DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->where('stocks.status', 'available')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.id as variant_id',
                'product_variants.sku',
                DB::raw('SUM(stocks.quantity) as current_stock'),
            ])
            ->groupBy('products.id', 'products.name', 'product_variants.id', 'product_variants.sku')
            ->havingRaw('SUM(stocks.quantity) <= ?', [$threshold])
            ->orderBy('current_stock', 'asc')
            ->get();

        return response()->json([
            'low_stock_items' => $lowStock,
            'threshold' => $threshold,
            'count' => $lowStock->count(),
        ]);
    }

    /**
     * @group 05. التقارير والتحليلات
     * 
     * المنتجات الراكدة
     * 
     * المنتجات التي لم يتم عليها أي حركة خلال فترة طويلة.
     * 
     * @queryParam days integer عدد الأيام. Example: 90
     */
    public function inactiveStock(Request $request)
    {
        $days = $request->input('days', 90);
        $cutoffDate = now()->subDays($days);

        $inactive = DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.status', 'available')
            ->where('stocks.updated_at', '<', $cutoffDate)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(stocks.quantity) as quantity'),
                DB::raw('MAX(stocks.updated_at) as last_movement'),
                DB::raw('DATEDIFF(NOW(), MAX(stocks.updated_at)) as days_inactive'),
            ])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('days_inactive')
            ->get();

        return response()->json([
            'inactive_items' => $inactive,
            'days_threshold' => $days,
            'count' => $inactive->count(),
        ]);
    }
}
