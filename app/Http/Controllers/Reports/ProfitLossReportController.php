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
            ->keyBy(fn($item) => $item->date->toDateString());

        // Generate full date range
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $details = collect();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $s = $summaries->get($dateStr);

            $details->push([
                'date' => $dateStr,
                'revenue' => $s ? (float) $s->total_revenue : 0,
                'cost_of_goods_sold' => $s ? (float) $s->total_cogs : 0,
                'expenses' => $s ? (float) $s->total_expenses : 0,
                'net_profit' => $s ? (float) $s->net_profit : 0,
            ]);
        }

        $summary = [
            'total_revenue' => (float) $summaries->sum('total_revenue'),
            'total_cost_of_goods_sold' => (float) $summaries->sum('total_cogs'),
            'total_expenses' => (float) $summaries->sum('total_expenses'),
            'total_costs' => (float) ($summaries->sum('total_cogs') + $summaries->sum('total_expenses')),
            'net_profit' => (float) $summaries->sum('net_profit'),
        ];

        return api_success([
            'summary' => $summary,
            'details' => $details
        ]);
    }
}
