<?php

namespace App\Http\Controllers\Reports;

use App\Models\DailySalesSummary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ProfitLossReportController extends BaseReportController
{
    /**
     * تقرير الأرباح والخسائر العام (يستخدم كمدخل رئيسي)
     */
    public function index(Request $request): JsonResponse
    {
        return $this->profitLossSummary($request);
    }

    /**
     * تقرير ملخص الأرباح والخسائر عالي الأداء
     */
    public function profitLossSummary(Request $request): JsonResponse
    {
        $dateFrom = $request->date_from ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? Carbon::now()->toDateString();
        $companyId = auth()->user()->company_id;

        // Fetch existing summaries
        $summaries = DailySalesSummary::where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->date)->toDateString());

        // Generate full date range
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $details = collect();

        foreach (new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->addDay()) as $date) {
            $dateStr = $date->format('Y-m-d');
            $s = $summaries->get($dateStr);

            $revenue = $s ? (float) $s->total_revenue : 0;
            $cogs = $s ? (float) $s->total_cogs : 0;
            $expenses = $s ? (float) $s->total_expenses : 0;

            // Fallback for COGS if it's 0 (or very low) but there is revenue
            if ($revenue > 0 && $cogs <= 0) {
                // Try to sum costs from invoice_items, with fallback hierarchy: 
                // 1. Snapshot cost 2. Catalog price 3. Latest stock cost
                $cogs = \DB::table('invoice_items')
                    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->leftJoin('product_variants', 'invoice_items.variant_id', '=', 'product_variants.id')
                    ->where('invoices.company_id', $companyId)
                    ->whereDate('invoices.created_at', $dateStr)
                    ->whereIn('invoices.status', ['paid', 'partial', 'confirmed', 'completed'])
                    ->whereNull('invoices.deleted_at')
                    ->sum(\DB::raw('COALESCE(
                        invoice_items.cost_price, 
                        product_variants.purchase_price, 
                        (SELECT cost FROM stocks WHERE variant_id = invoice_items.variant_id AND cost > 0 ORDER BY created_at DESC LIMIT 1),
                        0
                    ) * invoice_items.quantity')) ?: 0;
            }

            $details->push([
                'date' => $dateStr,
                'revenue' => (float) $revenue,
                'cost_of_goods_sold' => (float) $cogs,
                'expenses' => (float) $expenses,
                'net_profit' => (float) ($revenue - $cogs - $expenses),
            ]);
        }

        $summary = [
            'total_revenue' => (float) $details->sum('revenue'),
            'total_cost_of_goods_sold' => (float) $details->sum('cost_of_goods_sold'),
            'total_expenses' => (float) $details->sum('expenses'),
            'total_costs' => (float) ($details->sum('cost_of_goods_sold') + $details->sum('expenses')),
            'net_profit' => (float) $details->sum('net_profit'),
        ];

        return api_success([
            'summary' => $summary,
            'details' => $details,
            'details_count' => $details->count()
        ]);
    }

    /**
     * مقارنة الأداء الشهرية (للرسم البياني)
     */
    public function monthlyComparison(Request $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $monthsToFetch = 6;

        $data = \App\Models\MonthlySalesSummary::where('company_id', $companyId)
            ->orderBy('year_month', 'desc')
            ->limit($monthsToFetch)
            ->get()
            ->reverse()
            ->values();

        $formatted = $data->map(function ($item) use ($companyId) {
            $revenue = (float) $item->total_revenue;
            $cogs = (float) $item->total_cogs;
            $expenses = (float) $item->total_expenses;

            if ($revenue > 0 && $cogs <= 0) {
                $cogs = \DB::table('invoice_items')
                    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->leftJoin('product_variants', 'invoice_items.variant_id', '=', 'product_variants.id')
                    ->where('invoices.company_id', $companyId)
                    ->whereRaw("DATE_FORMAT(invoices.created_at, '%Y-%m') = ?", [$item->year_month])
                    ->whereIn('invoices.status', ['paid', 'partial', 'confirmed', 'completed'])
                    ->whereNull('invoices.deleted_at')
                    ->sum(\DB::raw('COALESCE(
                        invoice_items.cost_price, 
                        product_variants.purchase_price, 
                        (SELECT cost FROM stocks WHERE variant_id = invoice_items.variant_id AND cost > 0 ORDER BY created_at DESC LIMIT 1),
                        0
                    ) * invoice_items.quantity')) ?: 0;
            }

            return [
                'month' => $item->year_month,
                'revenue' => (float) $revenue,
                'costs' => (float) ($cogs + $expenses),
                'profit' => (float) ($revenue - $cogs - $expenses),
            ];
        });

        return api_success($formatted);
    }
}
