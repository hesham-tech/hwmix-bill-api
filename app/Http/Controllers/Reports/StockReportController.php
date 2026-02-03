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

        $query = DB::table('stocks')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('invoice_items', 'product_variants.id', '=', 'invoice_items.variant_id')
            ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
            ->select([
                'product_variants.product_id',
                'stocks.variant_id',
                'stocks.warehouse_id',
                DB::raw("SUM(CASE WHEN invoice_types.code = 'purchase' THEN invoice_items.quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN invoice_types.code = 'sale' THEN invoice_items.quantity ELSE 0 END) as total_out"),
                DB::raw("ANY_VALUE(stocks.quantity) as current_stock"), // Or use a separate subquery for precision if status matches
            ])
            ->where('stocks.company_id', auth()->user()->company_id)
            ->where('products.product_type', 'physical')
            ->groupBy('product_variants.product_id', 'stocks.variant_id', 'stocks.warehouse_id');

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('invoices.issue_date', '>=', $filters['date_from'])
                    ->orWhereNull('invoices.issue_date');
            });
        }

        if (!empty($filters['date_to'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('invoices.issue_date', '<=', $filters['date_to'])
                    ->orWhereNull('invoices.issue_date');
            });
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_variants.product_id', $filters['product_id']);
        }

        try {
            $report = $query->paginate($filters['per_page'] ?? 50);

            $summary = [
                'total_products' => Stock::whereCompanyIsCurrent()->distinct('variant_id')->count('variant_id'),
                'total_in' => (float) DB::table('invoice_items')
                    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
                    ->where('invoices.company_id', auth()->user()->company_id)
                    ->where('invoice_types.code', 'purchase')
                    ->sum('invoice_items.quantity'),
                'total_out' => (float) DB::table('invoice_items')
                    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
                    ->where('invoices.company_id', auth()->user()->company_id)
                    ->where('invoice_types.code', 'sale')
                    ->sum('invoice_items.quantity'),
                'current_stock' => (float) Stock::whereCompanyIsCurrent()->where('status', 'available')->sum('quantity'),
            ];

            return response()->json([
                'report' => $report,
                'summary' => $summary,
                'filters' => $filters,
            ]);
        } catch (\Exception $e) {
            \Log::error('Stock Report Error: ' . $e->getMessage());
            throw $e;
        }
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

        $valuationQuery = DB::table('stocks')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('stocks.status', 'available');

        if (!empty($filters['company_id'])) {
            $valuationQuery->where('stocks.company_id', $filters['company_id']);
        } elseif ($user = auth()->user()) {
            $valuationQuery->where('stocks.company_id', $user->company_id);
        }

        // Apply Status Filters
        if (!empty($filters['stock_status'])) {
            $threshold = $filters['threshold'] ?? 10;
            if ($filters['stock_status'] === 'in_stock') {
                $valuationQuery->havingRaw('SUM(stocks.quantity) > ?', [$threshold]);
            } elseif ($filters['stock_status'] === 'low_stock') {
                $valuationQuery->havingRaw('SUM(stocks.quantity) <= ? AND SUM(stocks.quantity) > 0', [$threshold]);
            } elseif ($filters['stock_status'] === 'out_of_stock') {
                $valuationQuery->havingRaw('SUM(stocks.quantity) <= 0');
            }
        }

        $valuation = $valuationQuery
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(stocks.quantity) as total_quantity'),
                DB::raw('AVG(COALESCE(NULLIF(stocks.cost, 0), product_variants.purchase_price)) as avg_cost'),
                DB::raw('SUM(stocks.quantity * COALESCE(NULLIF(stocks.cost, 0), product_variants.purchase_price)) as total_cost_value'),
                DB::raw('SUM(stocks.quantity * product_variants.retail_price) as total_sale_value'),
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

        $itemQuery = DB::table('stocks')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('stocks.status', 'available')
            ->where('products.product_type', 'physical');

        if ($user = auth()->user()) {
            $itemQuery->where('stocks.company_id', $user->company_id);
        }

        $lowStock = $itemQuery
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.id as variant_id',
                'product_variants.sku',
                DB::raw('SUM(stocks.quantity) as current_stock'),
            ])
            ->groupBy('products.id', 'products.name', 'product_variants.id', 'product_variants.sku')
            ->havingRaw('SUM(stocks.quantity) <= ? AND SUM(stocks.quantity) > 0', [$threshold])
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

        $isSqlite = DB::getDriverName() === 'sqlite';
        $datediff = $isSqlite
            ? "CAST(julianday('now') - julianday(MAX(stocks.updated_at)) AS INT)"
            : "DATEDIFF(NOW(), MAX(stocks.updated_at))";

        $inactiveQuery = DB::table('stocks')
            ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('stocks.status', 'available')
            ->where('stocks.updated_at', '<', $cutoffDate);

        if ($user = auth()->user()) {
            $inactiveQuery->where('stocks.company_id', $user->company_id);
        }

        $inactive = $inactiveQuery->select([
            'products.id as product_id',
            'products.name as product_name',
            DB::raw('SUM(stocks.quantity) as quantity'),
            DB::raw('MAX(stocks.updated_at) as last_movement'),
            DB::raw("{$datediff} as days_inactive"),
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
