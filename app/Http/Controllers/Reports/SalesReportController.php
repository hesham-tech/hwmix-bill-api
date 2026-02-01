<?php

namespace App\Http\Controllers\Reports;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends BaseReportController
{
    /**
     * @group 05. التقارير والتحليلات
     * 
     * تقرير المبيعات العام
     * 
     * توليد تقرير شامل للمبيعات مع إمكانية التجميع حسب (اليوم، الشهر، العميل، أو المنتج).
     * 
     * @queryParam date_from date تاريخ البداية. Example: 2024-01-01
     * @queryParam date_to date تاريخ النهاية.
     * @queryParam group_by string التجميع حسب (day, month, product, customer). Example: month
     * @queryParam export string تصدير التقرير (excel, csv).
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        // Base query for sales invoices
        $query = Invoice::query()
            ->whereHas('invoiceType', function ($q) {
                $q->where('code', 'sale');
            })
            ->with(['items.product', 'customer', 'invoiceType']);

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

        // 📊 إحصائيات الخدمات والاشتراكات
        $summary['services_summary'] = $this->getServicesSummary(clone $query);

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
     * @group 05. التقارير والتحليلات
     * 
     * المنتجات الأكثر مبيعاً
     * 
     * عرض قائمة بالمنتجات الأعلى مبيعاً خلال فترة محددة.
     * 
     * @queryParam limit integer عدد النتائج. Example: 10
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
     * @group 05. التقارير والتحليلات
     * 
     * كبار العملاء
     * 
     * عرض قائمة بالعملاء الأكثر شراءً.
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
     * @group 05. التقارير والتحليلات
     * 
     * اتجاهات المبيعات (Trend)
     * 
     * تحليل حركة المبيعات بمرور الوقت (يومياً، أسبوعياً، أو شهرياً).
     * 
     * @queryParam period string نوع الفترة (day, week, month). Example: week
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

        // Fill gaps if dates are provided
        if ($request->date_from && $request->date_to) {
            $trend = $this->fillDateGaps($trend, $request->date_from, $request->date_to, $period);
        }

        return response()->json([
            'trend' => $trend,
            'period_type' => $period,
        ]);
    }

    /**
     * Get summary of services and subscriptions sold
     */
    private function getServicesSummary($query): array
    {
        $invoiceIds = $query->pluck('id');

        $serviceItems = \DB::table('invoice_items')
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotNull('service_id')
            ->select([
                \DB::raw('COUNT(*) as count'),
                \DB::raw('SUM(total) as total_revenue'),
            ])
            ->first();

        $activeSubscriptions = \DB::table('subscriptions')
            ->whereIn('invoice_id', $invoiceIds) // Only if linked to this set of invoices
            ->orWhere(function ($q) use ($query) {
                // Or linked to the same customers in the same period
                $customerIds = $query->pluck('user_id');
                $q->whereIn('user_id', $customerIds);
            })
            ->where('status', 'active')
            ->count();

        return [
            'total_services_count' => $serviceItems->count ?? 0,
            'total_services_revenue' => round($serviceItems->total_revenue ?? 0, 2),
            'active_subscriptions_count' => $activeSubscriptions,
        ];
    }
}
