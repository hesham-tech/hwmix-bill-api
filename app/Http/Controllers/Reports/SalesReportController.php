<?php

namespace App\Http\Controllers\Reports;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends BaseReportController
{
    /**
     * Generate sales report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        // Base query for sales invoices
        $query = Invoice::query()
            ->whereHas('invoiceType', function ($q) {
                $q->where('code', 'sale');
            })
            ->with(['items.product', 'user', 'invoiceType']);

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Get grouping preference
        $groupBy = $filters['group_by'] ?? null;

        // Generate report based on grouping
        if ($groupBy) {
            $report = $this->generateGroupedReport($query, $groupBy);
        } else {
            $report = $query->latest()
                ->paginate($filters['per_page'] ?? 50);
        }

        // Calculate summary
        $summary = $this->calculateSummary(clone $query);

        // Add additional sales-specific metrics
        $summary['total_items_sold'] = $this->getTotalItemsSold($query);
        $summary['unique_customers'] = $query->distinct('user_id')->count('user_id');

        $result = [
            'report' => $report,
            'summary' => $summary,
            'filters' => $filters,
        ];

        // Handle export if requested
        if (!empty($filters['export'])) {
            return $this->export($result, $filters['export'], 'sales_report');
        }

        return response()->json($result);
    }

    /**
     * Generate grouped report
     */
    private function generateGroupedReport($query, string $groupBy)
    {
        return match ($groupBy) {
            'day', 'week', 'month', 'year' => $this->groupByPeriod($query, $groupBy),
            'product' => $this->groupByProduct($query),
            'customer' => $this->groupByCustomer($query),
            default => $query->get(),
        };
    }

    /**
     * Get total items sold
     */
    private function getTotalItemsSold($query): float
    {
        return DB::table('invoice_items')
            ->whereIn('invoice_id', $query->pluck('id'))
            ->sum('quantity');
    }

    /**
     * Get top selling products
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topProducts(Request $request)
    {
        $filters = $this->validateFilters($request);
        $limit = $request->input('limit', 10);

        $query = Invoice::query()
            ->whereHas('invoiceType', function ($q) {
                $q->where('code', 'sale');
            });

        $query = $this->applyFilters($query, $filters);

        $topProducts = $this->groupByProduct($query)->take($limit);

        return response()->json([
            'top_products' => $topProducts,
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
        ]);
    }

    /**
     * Get top customers
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topCustomers(Request $request)
    {
        $filters = $this->validateFilters($request);
        $limit = $request->input('limit', 10);

        $query = Invoice::query()
            ->whereHas('invoiceType', function ($q) {
                $q->where('code', 'sale');
            });

        $query = $this->applyFilters($query, $filters);

        $topCustomers = $this->groupByCustomer($query)->take($limit);

        return response()->json([
            'top_customers' => $topCustomers,
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
        ]);
    }

    /**
     * Get sales trend (daily/weekly/monthly)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trend(Request $request)
    {
        $filters = $this->validateFilters($request);
        $period = $request->input('period', 'month');

        $query = Invoice::query()
            ->whereHas('invoiceType', function ($q) {
                $q->where('code', 'sale');
            });

        $query = $this->applyFilters($query, $filters);

        $trend = $this->groupByPeriod($query, $period);

        return response()->json([
            'trend' => $trend,
            'period_type' => $period,
        ]);
    }
}
