<?php

namespace App\Http\Controllers\Reports;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitLossReportController extends BaseReportController
{
    /**
     * Generate Profit & Loss Report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();
        $companyId = $filters['company_id'] ?? null;

        // Calculate revenues
        $revenues = [
            'sales' => $this->getSalesRevenue($dateFrom, $dateTo, $companyId),
            'services' => $this->getServicesRevenue($dateFrom, $dateTo, $companyId),
            'subscriptions' => $this->getSubscriptionRevenue($dateFrom, $dateTo, $companyId),
            'other' => $this->getOtherRevenue($dateFrom, $dateTo, $companyId),
        ];

        $totalRevenue = collect($revenues)->sum();

        // Calculate costs
        $costs = [
            'purchases' => $this->getPurchasesCost($dateFrom, $dateTo, $companyId),
            'operating' => $this->getOperatingCost($dateFrom, $dateTo, $companyId),
            'salaries' => $this->getSalariesCost($dateFrom, $dateTo, $companyId),
            'other' => $this->getOtherCosts($dateFrom, $dateTo, $companyId),
        ];

        $totalCosts = collect($costs)->sum();

        // Calculate profit/loss
        $netProfit = $totalRevenue - $totalCosts;
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        $result = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'revenues' => [
                'breakdown' => $revenues,
                'total' => round($totalRevenue, 2),
            ],
            'costs' => [
                'breakdown' => $costs,
                'total' => round($totalCosts, 2),
            ],
            'result' => [
                'net_profit' => round($netProfit, 2),
                'profit_margin' => round($profitMargin, 2),
                'status' => $netProfit >= 0 ? 'profit' : 'loss',
            ],
        ];

        // Handle export if requested
        if (!empty($filters['export'])) {
            return $this->export($result, $filters['export'], 'profit_loss_report');
        }

        return response()->json($result);
    }

    /**
     * Get sales revenue
     */
    private function getSalesRevenue(string $from, string $to, ?int $companyId): float
    {
        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->sum('net_amount');
    }

    /**
     * Get services revenue
     */
    private function getServicesRevenue(string $from, string $to, ?int $companyId): float
    {
        // TODO: Implement when services invoices are tracked
        return 0;
    }

    /**
     * Get subscription revenue
     */
    private function getSubscriptionRevenue(string $from, string $to, ?int $companyId): float
    {
        // TODO: Implement when subscription system is complete
        return 0;
    }

    /**
     * Get other revenue
     */
    private function getOtherRevenue(string $from, string $to, ?int $companyId): float
    {
        // Placeholder for miscellaneous revenue
        return 0;
    }

    /**
     * Get purchases cost
     */
    private function getPurchasesCost(string $from, string $to, ?int $companyId): float
    {
        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->sum('net_amount');
    }

    /**
     * Get operating costs
     */
    private function getOperatingCost(string $from, string $to, ?int $companyId): float
    {
        // TODO: Implement when expense tracking is added
        return 0;
    }

    /**
     * Get salaries cost
     */
    private function getSalariesCost(string $from, string $to, ?int $companyId): float
    {
        // TODO: Implement when payroll system is added
        return 0;
    }

    /**
     * Get other costs
     */
    private function getOtherCosts(string $from, string $to, ?int $companyId): float
    {
        // Placeholder for miscellaneous costs
        return 0;
    }

    /**
     * Get monthly comparison
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyComparison(Request $request)
    {
        $filters = $this->validateFilters($request);
        $months = $request->input('months', 6); // Last 6 months by default

        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $date = now()->subMonths($i);
            $from = $date->copy()->startOfMonth()->toDateString();
            $to = $date->copy()->endOfMonth()->toDateString();

            $revenue = $this->getSalesRevenue($from, $to, $filters['company_id'] ?? null);
            $costs = $this->getPurchasesCost($from, $to, $filters['company_id'] ?? null);
            $profit = $revenue - $costs;

            $data[] = [
                'month' => $date->format('Y-m'),
                'revenue' => round($revenue, 2),
                'costs' => round($costs, 2),
                'profit' => round($profit, 2),
                'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        }

        return response()->json([
            'comparison' => array_reverse($data),
            'months_count' => $months,
        ]);
    }
}
