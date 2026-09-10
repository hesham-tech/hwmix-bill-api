<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ItemProfitabilityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->active_company_id;

        $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $period = $request->input('period');

        if ($period) {
            switch ($period) {
                case 'today':
                    $dateFrom = Carbon::today()->toDateString();
                    $dateTo = Carbon::today()->toDateString();
                    break;
                case 'week':
                    $dateFrom = Carbon::now()->startOfWeek()->toDateString();
                    $dateTo = Carbon::now()->endOfWeek()->toDateString();
                    break;
                case 'month':
                    $dateFrom = Carbon::now()->startOfMonth()->toDateString();
                    $dateTo = Carbon::now()->endOfMonth()->toDateString();
                    break;
                case 'year':
                    $dateFrom = Carbon::now()->startOfYear()->toDateString();
                    $dateTo = Carbon::now()->endOfYear()->toDateString();
                    break;
            }
        }

        $baseQuery = InvoiceItem::where('invoice_items.company_id', $companyId)
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.invoice_type_code', ['sale', 'installment_sale'])
            ->whereIn('invoices.payment_status', ['paid', 'partially_paid'])
            ->whereBetween(DB::raw('DATE(invoices.issue_date)'), [$dateFrom, $dateTo]);

        $summaryQuery = clone $baseQuery;
        $summary = $summaryQuery->select(
            DB::raw('SUM(invoice_items.quantity) as total_qty'),
            DB::raw('SUM(invoice_items.total_cost) as total_cost'),
            DB::raw('SUM(invoice_items.subtotal) as total_revenue')
        )->first();

        $totalRevenue = (float) ($summary->total_revenue ?? 0);
        $totalCost = (float) ($summary->total_cost ?? 0);
        $totalProfit = $totalRevenue - $totalCost;

        $perPage = $request->input('per_page', 15);
        
        $items = $baseQuery->select(
            'invoice_items.id',
            'invoice_items.invoice_id',
            'invoices.invoice_number',
            'invoices.issue_date',
            'invoice_items.name',
            'invoice_items.quantity',
            'invoice_items.unit_price',
            'invoice_items.cost_price',
            'invoice_items.subtotal',
            'invoice_items.total_cost'
        )
        ->orderBy('invoices.issue_date', 'desc')
        ->paginate($perPage);

        $items->getCollection()->transform(function ($item) {
            $item->profit = $item->subtotal - $item->total_cost;
            return $item;
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_qty' => (int) ($summary->total_qty ?? 0),
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
            ],
            'items' => $items
        ]);
    }
}

