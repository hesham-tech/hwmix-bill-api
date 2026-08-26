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
        $companyId = auth()->user()->active_company_id;

        // Fetch existing summaries
        $summaries = DailySalesSummary::where('company_id', $companyId)
            ->whereBetween('date', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ])
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

        return response()->json([
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'revenues' => [
                'total' => (float) $details->sum('revenue'),
            ],
            'costs' => [
                'total' => (float) ($details->sum('cost_of_goods_sold') + $details->sum('expenses')),
                'cost_of_goods_sold' => (float) $details->sum('cost_of_goods_sold'),
                'expenses' => (float) $details->sum('expenses'),
            ],
            'result' => [
                'net_profit' => (float) $details->sum('net_profit'),
            ],
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
        $companyId = auth()->user()->active_company_id;
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

            

            return [
                'month' => $item->year_month,
                'revenue' => (float) $revenue,
                'costs' => (float) ($cogs + $expenses),
                'profit' => (float) ($revenue - $cogs - $expenses),
            ];
        });

        return response()->json([
            'comparison' => $formatted,
            'months_count' => count($formatted),
        ]);
    }
}
