<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Installment;
use App\Models\Product;
use App\Models\User;
use App\Models\CompanyUser;
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
        $user = $request->user();
        $companyId = $user->company_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        // فحص ما إذا كان المستخدم عميلاً (ليس لديه صلاحيات إدارية كافية ليكون موظفاً)
        $isCustomer = !$user->hasAnyRole(['admin.super', 'admin.company', 'manager', 'accountant', 'sales', 'stock']) && !$user->hasPermissionTo('admin.page');

        if ($isCustomer) {
            // --- لوحة تحكم العميل ---
            $stats = [
                'total_invoices' => Invoice::where('user_id', $user->id)->count(),
                'total_paid' => InvoicePayment::whereHas('invoice', fn($q) => $q->where('user_id', $user->id))->sum('amount'),
                'remaining_balance' => Invoice::where('user_id', $user->id)->count() > 0 ? Invoice::where('user_id', $user->id)->sum('remaining_amount') : 0,
                'upcoming_installments_count' => Installment::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->where('due_date', '>=', $now)
                    ->count(),
            ];

            $recentInvoices = Invoice::with(['invoiceType', 'items.product', 'payments.paymentMethod'])
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get();

            $recentPayments = InvoicePayment::with(['invoice', 'paymentMethod'])
                ->whereHas('invoice', fn($q) => $q->where('user_id', $user->id))
                ->latest()
                ->limit(5)
                ->get();

            $upcomingInstallments = Installment::with(['installmentPlan.invoice'])
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('due_date', '>=', $now)
                ->orderBy('due_date', 'asc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'customer',
                    'kpis' => $stats,
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'upcoming_installments' => $upcomingInstallments,
                ]
            ]);
        }

        // --- لوحة تحكم الإدارة (الأصلية مع تحسينات طفيفة) ---
        $stats = [
            'total_sales' => Invoice::where('company_id', $companyId)
                ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
                ->sum('net_amount'),

            'monthly_sales' => Invoice::where('company_id', $companyId)
                ->whereHas('invoiceType', fn($q) => $q->where('code', 'sale'))
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->sum('net_amount'),

            'pending_payments' => Invoice::where('company_id', $companyId)
                ->where('remaining_amount', '>', 0)
                ->sum('remaining_amount'),

            'total_customers' => CompanyUser::where('company_id', $companyId)
                ->where('role', 'customer')
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
                'role' => 'admin',
                'kpis' => $stats,
                'sales_trend' => $salesTrend,
                'recent_invoices' => $recentInvoices,
                'top_products' => $topProducts,
            ]
        ]);
    }
}
