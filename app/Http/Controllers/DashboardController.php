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

        // فحص ما إذا كان المستخدم عميلاً
        $isCustomer = !$user->hasAnyRole(['admin.super', 'admin.company', 'manager', 'accountant', 'sales', 'stock']) && !$user->hasPermissionTo('admin.page');

        // استراتيجية النسخة (Cache Versioning) لتسهيل التنظيف
        $version = \Illuminate\Support\Facades\Cache::get("dashboard_version_{$companyId}", 1);
        $cacheKey = "dashboard_stats_comp_{$companyId}_user_{$user->id}_v{$version}";

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $companyId, $now, $startOfMonth, $isCustomer) {
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

                return [
                    'role' => 'customer',
                    'kpis' => $stats,
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'upcoming_installments' => $upcomingInstallments,
                ];
            }

            // --- لوحة تحكم الإدارة ---
            $stats = [
                'total_sales' => Invoice::where('company_id', $companyId)
                    ->where('invoice_type_code', 'sale')
                    ->sum('net_amount'),

                'monthly_sales' => Invoice::where('company_id', $companyId)
                    ->where('invoice_type_code', 'sale')
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
                ->where('invoice_type_code', 'sale')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(net_amount) as total')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // 3. أحدث العمليات
            $recentInvoices = Invoice::with(['customer', 'invoiceType'])
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

            return [
                'role' => 'admin',
                'kpis' => $stats,
                'sales_trend' => $salesTrend,
                'recent_invoices' => $recentInvoices,
                'top_products' => $topProducts,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
