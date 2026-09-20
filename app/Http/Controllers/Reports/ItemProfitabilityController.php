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
            ->whereIn('invoices.invoice_type_code', ['sale', 'installment_sale', 'sale_return'])
            ->whereNotIn('invoices.status', ['draft', 'canceled'])
            ->whereBetween(DB::raw('COALESCE(DATE(invoices.issue_date), DATE(invoices.created_at))'), [$dateFrom, $dateTo]);

        $summaryQuery = clone $baseQuery;
        $summary = $summaryQuery->select(
            DB::raw('SUM(CASE WHEN invoices.invoice_type_code = "sale_return" THEN -invoice_items.quantity ELSE invoice_items.quantity END) as total_qty'),
            DB::raw('SUM(CASE WHEN invoices.invoice_type_code = "sale_return" THEN -invoice_items.total_cost ELSE invoice_items.total_cost END) as total_cost'),
            DB::raw('SUM(CASE WHEN invoices.invoice_type_code = "sale_return" THEN -invoice_items.subtotal ELSE invoice_items.subtotal END) as total_revenue')
        )->first();

        $totalRevenue = (float) ($summary->total_revenue ?? 0);
        $totalCost = (float) ($summary->total_cost ?? 0);
        $totalProfit = $totalRevenue - $totalCost;

        $perPage = $request->input('per_page', 15);
        
        $items = $baseQuery->select(
            'invoice_items.id',
            'invoice_items.invoice_id',
            'invoices.invoice_number',
            'invoices.invoice_type_code',
            DB::raw('COALESCE(invoices.issue_date, invoices.created_at) as issue_date'),
            'invoice_items.name',
            'invoice_items.quantity',
            'invoice_items.unit_price',
            'invoice_items.cost_price',
            'invoice_items.subtotal',
            'invoice_items.total_cost'
        )
        ->orderByRaw('COALESCE(invoices.issue_date, invoices.created_at) DESC')
        ->paginate($perPage);

        $items->getCollection()->transform(function ($item) {
            $sign = ($item->invoice_type_code === 'sale_return') ? -1 : 1;
            
            $item->quantity = $item->quantity * $sign;
            $item->subtotal = $item->subtotal * $sign;
            $item->total_cost = $item->total_cost * $sign;
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

