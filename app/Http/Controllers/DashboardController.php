<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * @group 05. التقارير والتحليلات
     * 
     * مؤشرات لوحة التحكم (Dashboard)
     * 
     * جلب الإحصائيات الحيوية للنظام (إجمالي المبيعات، نمو العملاء، المنتجات الأعلى مبيعاً) لعرضها في الشاشة الرئيسية.
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        // 1. مؤشرات الأداء الرئيسية (KPIs)
        $stats = [
            'total_sales' => Invoice::where('company_id', $companyId)
                ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
                ->sum('net_amount'),

            'monthly_sales' => Invoice::where('company_id', $companyId)
                ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->sum('net_amount'),

            'pending_payments' => Invoice::where('company_id', $companyId)
                ->sum('remaining_amount'),

            'total_customers' => User::where('company_id', $companyId)
                ->where('is_supplier', false)
                ->count(),

            'total_products' => Product::where('company_id', $companyId)->count(),
        ];

        // 2. تحليل المبيعات (آخر 7 أيام)
        $salesTrend = Invoice::where('company_id', $companyId)
            ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(net_amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 3. أحدث العمليات
        $recentInvoices = Invoice::with(['user', 'invoiceType'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit(5)
            ->get();

        // 4. المنتجات الأعلى مبيعاً
        $topProducts = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('products.company_id', $companyId)
            ->select('products.name', DB::raw('SUM(invoice_items.quantity) as total_qty'))
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => $stats,
                'sales_trend' => $salesTrend,
                'recent_invoices' => $recentInvoices,
                'top_products' => $topProducts,
            ]
        ]);
    }
}
