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
            ->with(['cashBox', 'user'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        // Group by type
        $byType = $query->select([
            'type',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total_amount'),
        ])
            ->groupBy('type')
            ->get();

        $totalDeposits = $byType->where('type', 'deposit')->sum('total_amount');
        $totalWithdrawals = $byType->where('type', 'withdraw')->sum('total_amount');
        $netCashFlow = $totalDeposits - $totalWithdrawals;

        $result = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'breakdown' => [
                'deposits' => round($totalDeposits, 2),
                'withdrawals' => round($totalWithdrawals, 2),
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

        $byCashBox = DB::table('transactions')
            ->join('cash_boxes', 'transactions.cash_box_id', '=', 'cash_boxes.id')
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->select([
                'cash_boxes.id as cash_box_id',
                'cash_boxes.name as cash_box_name',
                DB::raw('SUM(CASE WHEN transactions.type = "deposit" THEN transactions.amount ELSE 0 END) as total_deposits'),
                DB::raw('SUM(CASE WHEN transactions.type = "withdraw" THEN transactions.amount ELSE 0 END) as total_withdrawals'),
                DB::raw('SUM(CASE WHEN transactions.type = "deposit" THEN transactions.amount ELSE -transactions.amount END) as net_flow'),
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

        $query = CashBox::query()->with('cashBoxType');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $cashBoxes = $query->get();

        $summary = [
            'total_cash_boxes' => $cashBoxes->count(),
            'total_balance' => round($cashBoxes->sum('balance'), 2),
            'by_type' => $cashBoxes->groupBy('cash_box_type_id')->map(function ($boxes, $typeId) {
                return [
                    'type' => $boxes->first()->cashBoxType->name ?? 'Unknown',
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
}
