<?php

namespace App\Http\Controllers\Reports;

use App\Models\Transaction;
use App\Models\CashBox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashFlowReportController extends BaseReportController
{
    /**
     * @group 05. التقارير المالية
     * 
     * تقرير التدفق النقدي
     * 
     * تحليل الحركات المالية (إيداعات وسحوبات) خلال فترة زمنية محددة.
     * 
     * @queryParam date_from date تاريخ البداية. Example: 2023-10-01
     * @queryParam date_to date تاريخ النهاية. Example: 2023-10-31
     * @queryParam company_id integer معرف الشركة.
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $query = Transaction::query()
            ->with(['cashbox', 'user'])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        } elseif (method_exists(Transaction::class, 'scopeWhereCompanyIsCurrent')) {
            $query->whereCompanyIsCurrent();
        }

        // Group by type (Clone query to avoid mutating base query)
        $byType = (clone $query)->select([
            'type',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total_amount'),
        ])
            ->groupBy('type')
            ->get();

        // Standardize types for reporting
        $inflowTypes = ['deposit', 'income', 'transfer_in'];
        $outflowTypes = ['withdraw', 'expense', 'transfer_out'];

        $totalInflow = $byType->whereIn('type', $inflowTypes)->sum('total_amount');
        $totalOutflow = $byType->whereIn('type', $outflowTypes)->sum('total_amount');
        $netCashFlow = $totalInflow - $totalOutflow;

        $result = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'breakdown' => [
                'deposits' => round($totalInflow, 2),
                'withdrawals' => round($totalOutflow, 2),
                'net_cash_flow' => round($netCashFlow, 2),
            ],
            'by_type' => $byType,
            'transactions' => $query->latest()->paginate($filters['per_page'] ?? 50),
        ];

        return response()->json($result);
    }

    /**
     * @group 05. التقارير المالية
     * 
     * التدفق النقدي حسب الخزنة
     * 
     * @queryParam date_from date تاريخ البداية. Example: 2023-10-01
     * @queryParam date_to date تاريخ النهاية. Example: 2023-10-31
     */
    public function byCashBox(Request $request)
    {
        $filters = $this->validateFilters($request);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $byCashBoxQuery = DB::table('transactions')
            ->join('cash_boxes', 'transactions.cashbox_id', '=', 'cash_boxes.id')
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo);

        if ($user = auth()->user()) {
            $byCashBoxQuery->where('transactions.company_id', $user->company_id);
        }

        $byCashBox = $byCashBoxQuery
            ->select([
                'cash_boxes.id as cash_box_id',
                'cash_boxes.name as cash_box_name',
                DB::raw("SUM(CASE WHEN transactions.type = 'deposit' THEN transactions.amount ELSE 0 END) as total_deposits"),
                DB::raw("SUM(CASE WHEN transactions.type = 'withdraw' THEN transactions.amount ELSE 0 END) as total_withdrawals"),
                DB::raw("SUM(CASE WHEN transactions.type = 'deposit' THEN transactions.amount ELSE -transactions.amount END) as net_flow"),
            ])
            ->groupBy('cash_boxes.id', 'cash_boxes.name')
            ->get();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'by_cash_box' => $byCashBox,
        ]);
    }

    /**
     * @group 05. التقارير المالية
     * 
     * ملخص السيولة الحالية
     * 
     * عرض أرصدة جميع الخزن وتصنيفاتها.
     * 
     * @queryParam company_id integer معرف الشركة.
     */
    public function summary(Request $request)
    {
        $companyId = $request->input('company_id');

        $query = CashBox::query()->with('typeBox');

        if ($companyId) {
            $query->where('company_id', $companyId);
        } elseif (method_exists(CashBox::class, 'scopeWhereCompanyIsCurrent')) {
            $query->whereCompanyIsCurrent();
        }

        $cashBoxes = $query->get();

        $summary = [
            'total_cash_boxes' => $cashBoxes->count(),
            'total_balance' => round($cashBoxes->sum('balance'), 2),
            'by_type' => $cashBoxes->groupBy('cash_box_type_id')->map(function ($boxes, $typeId) {
                return [
                    'type' => $boxes->first()->typeBox->name ?? 'Unknown',
                    'count' => $boxes->count(),
                    'total_balance' => round($boxes->sum('balance'), 2),
                ];
            })->values(),
            'details' => $cashBoxes->map(function ($box) {
                return [
                    'id' => $box->id,
                    'name' => $box->name,
                    'type' => $box->cashBoxType->name ?? 'Unknown',
                    'balance' => round($box->balance, 2),
                    'status' => $box->balance > 0 ? 'positive' : ($box->balance < 0 ? 'negative' : 'zero'),
                ];
            }),
        ];

        return response()->json($summary);
    }
    /**
     * @group 05. التقارير المالية
     * 
     * اتجاه التدفق النقدي (التدفقات الداخلة والخارجة عبر الزمن)
     */
    public function trend(Request $request)
    {
        $filters = $this->validateFilters($request);
        $period = $request->input('period', 'day');
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $query = Transaction::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $isSqlite = DB::getDriverName() === 'sqlite';
        $dateFormat = match ($period) {
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        $selectRaw = $isSqlite
            ? "strftime('{$dateFormat}', created_at) as period"
            : "DATE_FORMAT(created_at, '{$dateFormat}') as period";

        $trend = $query->selectRaw("
                {$selectRaw},
                SUM(CASE WHEN type IN ('deposit', 'income', 'transfer_in') THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type IN ('withdraw', 'expense', 'transfer_out') THEN amount ELSE 0 END) as total_out
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Fill gaps
        $trend = $this->fillDateGaps($trend, $dateFrom, $dateTo, $period);

        return response()->json([
            'trend' => $trend,
            'period_type' => $period,
        ]);
    }
}
