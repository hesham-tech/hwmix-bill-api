<?php

namespace App\Http\Controllers\Reports;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxReportController extends BaseReportController
{
    /**
     * Tax summary report
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

        $collected = $this->getTaxCollected($dateFrom, $dateTo, $companyId);
        $paid = $this->getTaxPaid($dateFrom, $dateTo, $companyId);
        $netTax = $collected - $paid;

        $result = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'summary' => [
                'tax_collected' => round($collected, 2),
                'tax_paid' => round($paid, 2),
                'net_tax_payable' => round($netTax, 2),
                'status' => $netTax > 0 ? 'payable' : ($netTax < 0 ? 'refundable' : 'neutral'),
            ],
        ];

        return response()->json($result);
    }

    /**
     * Get collected tax (from sales)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function collected(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $invoices = $query->select([
            'id',
            'invoice_number',
            'created_at',
            'user_id',
            'total_tax',
            'tax_rate',
            'tax_inclusive',
        ])
            ->with('user:id,name')
            ->paginate($filters['per_page'] ?? 50);

        $totalCollected = $query->sum('total_tax');

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_collected' => round($totalCollected, 2),
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get paid tax (from purchases)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paid(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $invoices = $query->select([
            'id',
            'invoice_number',
            'created_at',
            'user_id',
            'total_tax',
            'tax_rate',
            'tax_inclusive',
        ])
            ->with('user:id,name')
            ->paginate($filters['per_page'] ?? 50);

        $totalPaid = $query->sum('total_tax');

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_paid' => round($totalPaid, 2),
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get net tax (collected - paid)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function netTax(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();
        $companyId = $filters['company_id'] ?? null;

        $collected = $this->getTaxCollected($dateFrom, $dateTo, $companyId);
        $paid = $this->getTaxPaid($dateFrom, $dateTo, $companyId);
        $netTax = $collected - $paid;

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'collected' => round($collected, 2),
            'paid' => round($paid, 2),
            'net_tax' => round($netTax, 2),
            'recommendation' => $netTax > 0
                ? "يجب دفع {$netTax} للحكومة"
                : ($netTax < 0
                    ? "يمكن استرداد " . abs($netTax) . " من الحكومة"
                    : "لا توجد ضرائب مستحقة"),
        ]);
    }

    /**
     * Helper: Get tax collected from sales
     */
    private function getTaxCollected(string $from, string $to, ?int $companyId): float
    {
        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->sum('total_tax');
    }

    /**
     * Helper: Get tax paid on purchases
     */
    private function getTaxPaid(string $from, string $to, ?int $companyId): float
    {
        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['confirmed', 'paid', 'partially_paid']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->sum('total_tax');
    }
}
