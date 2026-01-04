<?php

namespace App\Http\Controllers\Reports;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerSupplierReportController extends BaseReportController
{
    /**
     * Top customers report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topCustomers(Request $request)
    {
        $filters = $this->validateFilters($request);
        $limit = $request->input('limit', 10);

        $dateFrom = $filters['date_from'] ?? now()->subMonths(6)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->whereDate('invoices.created_at', '>=', $dateFrom)
            ->whereDate('invoices.created_at', '<=', $dateTo)
            ->whereIn('invoices.status', ['confirmed', 'paid', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        } elseif (method_exists(Invoice::class, 'scopeWhereCompanyIsCurrent')) {
            $query->whereCompanyIsCurrent();
        }

        $topCustomers = $this->groupByCustomer($query)->take($limit);

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'top_customers' => $topCustomers,
        ]);
    }

    /**
     * @group 05. التقارير والتحليلات
     * 
     * تقرير أرصدة العملاء والموردين
     * 
     * عرض إجمالي المديونيات والدائنية لكل مستخدم.
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['confirmed', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        } elseif (method_exists(Invoice::class, 'scopeWhereCompanyIsCurrent')) {
            $query->whereCompanyIsCurrent();
        }

        $customerDebts = $query->selectRaw('
                user_id,
                SUM(remaining_amount) as total_debt
            ')
            ->groupBy('user_id')
            ->get();

        $supplierQuery = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['confirmed', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $supplierQuery->where('company_id', $filters['company_id']);
        } elseif (method_exists(Invoice::class, 'scopeWhereCompanyIsCurrent')) {
            $supplierQuery->whereCompanyIsCurrent();
        }

        $supplierDebts = $supplierQuery->selectRaw('
                user_id,
                SUM(remaining_amount) as total_debt
            ')
            ->groupBy('user_id')
            ->get();

        $allUsers = User::whereIn('id', $customerDebts->pluck('user_id')->merge($supplierDebts->pluck('user_id')))->get(['id', 'full_name', 'email']);

        $report = $allUsers->map(function ($user) use ($customerDebts, $supplierDebts) {
            $customerDebt = $customerDebts->where('user_id', $user->id)->first();
            $supplierDebt = $supplierDebts->where('user_id', $user->id)->first();

            return [
                'user_id' => $user->id,
                'user_name' => $user->full_name,
                'user_email' => $user->email,
                'total_customer_debt' => round($customerDebt->total_debt ?? 0, 2),
                'total_supplier_debt' => round($supplierDebt->total_debt ?? 0, 2),
            ];
        })->sortByDesc(function ($user) {
            return $user['total_customer_debt'] + $user['total_supplier_debt'];
        })->values();

        return response()->json([
            'customer_supplier_balances' => $report,
        ]);
    }

    /**
     * Customer debts report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerDebts(Request $request)
    {
        $filters = $this->validateFilters($request);

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['confirmed', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $debts = $query->selectRaw('
                user_id,
                COUNT(*) as unpaid_invoices,
                SUM(remaining_amount) as total_debt,
                MIN(due_date) as oldest_due_date,
                MAX(due_date) as latest_due_date
            ')
            ->with('user:id,full_name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_debt')
            ->paginate($filters['per_page'] ?? 50);

        $totalDebt = $query->sum('remaining_amount');

        return response()->json([
            'total_debt' => round($totalDebt, 2),
            'customer_debts' => $debts,
        ]);
    }

    /**
     * Supplier debts report (amounts we owe)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function supplierDebts(Request $request)
    {
        $filters = $this->validateFilters($request);

        $query = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'purchase'))
            ->where('remaining_amount', '>', 0)
            ->whereIn('status', ['confirmed', 'partially_paid']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $debts = $query->selectRaw('
                user_id,
                COUNT(*) as unpaid_invoices,
                SUM(remaining_amount) as total_debt,
                MIN(due_date) as oldest_due_date,
                MAX(due_date) as latest_due_date
            ')
            ->with('user:id,full_name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_debt')
            ->paginate($filters['per_page'] ?? 50);

        $totalDebt = $query->sum('remaining_amount');

        return response()->json([
            'total_debt_to_suppliers' => round($totalDebt, 2),
            'supplier_debts' => $debts,
        ]);
    }

    /**
     * @group 05. التقارير والتحليلات
     * 
     * كشف حساب تفصيلي
     * 
     * عرض جميع الحركات المالية والفواتير المرتبطة بمستخدم معين خلال فترة.
     * 
     * @queryParam user_id integer required معرف العميل/المورد. Example: 1
     * @queryParam date_from date تاريخ البداية.
     */
    public function statement(Request $request)
    {
        $userId = $request->input('user_id');
        $filters = $this->validateFilters($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $dateFrom = $filters['date_from'] ?? now()->subYear()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $invoices = Invoice::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['invoiceType', 'company'])
            ->orderBy('created_at')
            ->get();

        $balance = 0;
        $statement = $invoices->map(function ($invoice) use (&$balance) {
            $amount = $invoice->net_amount;
            $type = $invoice->invoiceType->code;

            if ($type === 'sale') {
                $balance += $amount;
            } elseif ($type === 'purchase') {
                $balance -= $amount;
            }

            return [
                'date' => $invoice->created_at->toDateString(),
                'type' => $type,
                'invoice_number' => $invoice->invoice_number,
                'description' => $invoice->description,
                'amount' => round($amount, 2),
                'current_balance' => round($balance, 2),
            ];
        });

        return response()->json([
            'user' => User::find($userId, ['id', 'full_name', 'email']),
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'statement' => $statement,
            'final_balance' => round($balance, 2),
        ]);
    }

    /**
     * Customer performance analysis
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function performance(Request $request)
    {
        $userId = $request->input('user_id');
        $filters = $this->validateFilters($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $dateFrom = $filters['date_from'] ?? now()->subYear()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        // Sales to this customer
        $salesQuery = Invoice::query()
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $salesStats = [
            'total_invoices' => $salesQuery->count(),
            'total_amount' => round($salesQuery->sum('net_amount'), 2),
            'total_paid' => round($salesQuery->sum('paid_amount'), 2),
            'total_remaining' => round($salesQuery->sum('remaining_amount'), 2),
            'average_invoice' => $salesQuery->count() > 0
                ? round($salesQuery->avg('net_amount'), 2)
                : 0,
        ];

        // Monthly trend
        $isSqlite = \DB::getDriverName() === 'sqlite';
        $monthFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyTrend = $salesQuery->selectRaw("
                {$monthFormat} as month,
                COUNT(*) as invoice_count,
                SUM(net_amount) as total
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top products purchased
        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('invoices.created_at', [$dateFrom, $dateTo])
            ->select([
                'products.name',
                DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.total) as total_spent'),
            ])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'user' => User::find($userId, ['id', 'full_name', 'email']),
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'sales_stats' => $salesStats,
            'monthly_trend' => $monthlyTrend,
            'top_products' => $topProducts,
        ]);
    }
}
