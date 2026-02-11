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

        // فحص ما إذا كان المستخدم عميلاً (ليس لديه صلاحيات إدارية)
        $isCustomer = !$user->hasAnyPermission([
            perm_key('admin.super'),
            perm_key('admin.company'),
            'admin.page',
            perm_key('users.view_all')
        ]);

        // استراتيجية النسخة (Cache Versioning) لتسهيل التنظيف
        $version = \Illuminate\Support\Facades\Cache::get("dashboard_version_{$companyId}", '3.1');
        $cacheKey = "dashboard_stats_comp_{$companyId}_user_{$user->id}_v{$version}_refactored";

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $companyId, $isCustomer) {
            if ($isCustomer) {
                return $this->getCustomerDashboardData($user);
            }
            return $this->getAdminDashboardData($companyId);
        });

        \Log::info('Dashboard Response for Request', [
            'company_id' => $request->user()->company_id,
            'data_kpis' => $data['kpis'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * جلب بيانات داشبورد العميل
     */
    private function getCustomerDashboardData($user)
    {
        $now = Carbon::now();
        $tenDaysLater = $now->copy()->addDays(10)->endOfDay();

        $stats = [
            'total_invoices' => Invoice::where('user_id', $user->id)->count(),
            'total_paid' => (float) \App\Models\Payment::where('user_id', $user->id)->sum('amount'),
            'remaining_balance' => (float) $user->balance,
            'active_installment_plans' => \App\Models\InstallmentPlan::where('user_id', $user->id)->where('status', '!=', 'paid')->count(),
            'upcoming_installments_count' => Installment::where('user_id', $user->id)
                ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
                ->where('due_date', '<=', $tenDaysLater)
                ->count(),
        ];

        $recentInvoices = Invoice::with(['invoiceType', 'items.product', 'payments.paymentMethod', 'installmentPlan.installments'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $recentPayments = \App\Models\Payment::with(['paymentMethod'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $upcomingInstallments = Installment::with(['installmentPlan.invoice.items.product'])
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
            ->where('due_date', '<=', $tenDaysLater)
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        return [
            'role' => 'customer',
            'kpis' => $stats,
            'recent_invoices' => $recentInvoices,
            'recent_payments' => $recentPayments,
            'upcoming_installments' => $upcomingInstallments,
        ];
    }

    /**
     * جلب بيانات داشبورد الإدارة
     */
    private function getAdminDashboardData($companyId)
    {
        $now = Carbon::now();
        $currentMonth = $now->format('Y-m');

        $monthlyStats = \App\Models\MonthlySalesSummary::where('company_id', $companyId)
            ->where('year_month', $currentMonth)
            ->first();

        $totalSales = \App\Models\MonthlySalesSummary::where('company_id', $companyId)->sum('total_revenue');

        $stats = [
            'total_sales' => (float) $totalSales,
            'monthly_sales' => (float) ($monthlyStats?->total_revenue ?? 0),
            'pending_payments' => (float) Invoice::where('company_id', $companyId)
                ->where('remaining_amount', '>', 0)
                ->whereIn('status', ['confirmed', 'partial'])
                ->sum('remaining_amount'),
            'total_customers' => CompanyUser::where('company_id', $companyId)
                ->where('role', 'customer')
                ->count(),
            'total_products' => Product::where('company_id', $companyId)->count(),
        ];

        $salesTrend = \App\Models\DailySalesSummary::where('company_id', $companyId)
            ->where('date', '>=', $now->copy()->subDays(7)->toDateString())
            ->orderBy('date', 'asc')
            ->get(['date', 'total_revenue as total']);

        $recentInvoices = Invoice::with(['customer', 'invoiceType'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit(5)
            ->get();

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
    }
}
