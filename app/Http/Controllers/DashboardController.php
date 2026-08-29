<?php

namespace App\Http\Controllers;

// متحكم لإدارة وعرض مؤشرات الأداء والتحليلات المالية والتشغيلية للوحة التحكم (Dashboard).

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Installment;
use Modules\Inventory\Models\Product;
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
        $companyId = $user->active_company_id;

        // فحص ما إذا كان المستخدم عميلاً (ليس لديه صلاحيات إدارية)
        $isCustomer = !$user->hasAnyPermission([
            perm_key('admin.super'),
            perm_key('admin.company'),
            'admin.page',
            perm_key('users.view_all')
        ]);

        $period = $request->get('period', 'month');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        // استراتيجية النسخة (Cache Versioning) لتسهيل التنظيف
        $version = \Illuminate\Support\Facades\Cache::get("dashboard_version_{$companyId}", '3.2');
        $cacheKey = "dash_stats_{$companyId}_u_{$user->id}_v{$version}_p{$period}_{$dateFrom}_{$dateTo}";

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes((int)env('DASHBOARD_CACHE_TTL', 15)), function () use ($user, $companyId, $isCustomer, $request) {
            if ($isCustomer) {
                return $this->getCustomerDashboardData($user);
            }
            return $this->getAdminDashboardData($companyId, $request);
        });

        \Log::info('Dashboard Response for Request', [
            'company_id' => $companyId,
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
            'remaining_balance' => (float) $user->getFinancialBalance($user->active_company_id, 'receivable'),
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
    private function getAdminDashboardData($companyId, $request = null)
    {
        $now = Carbon::now();
        $period = $request ? $request->get('period', 'month') : 'month';
        
        if ($request && $request->has('date_from') && $request->has('date_to')) {
            $startDate = Carbon::parse($request->get('date_from'))->startOfDay();
            $endDate = Carbon::parse($request->get('date_to'))->endOfDay();
        } else {
            if ($period === 'today') {
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
            } elseif ($period === 'week') {
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
            } else {
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
            }
        }

        // حساب إجمالي المبيعات (مدى الحياة)
        $totalSales = \App\Models\DailySalesSummary::where('company_id', $companyId)->sum('total_revenue');

        // حساب إيرادات الفترة المحددة بدقة
        $periodSales = \App\Models\Invoice::where('company_id', $companyId)
            ->whereIn('invoice_type_id', [2]) // فاتورة بيع
            ->whereBetween('issue_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('net_amount');

        // حساب مصروفات الفترة المحددة
        $periodExpenses = \App\Models\Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['cancelled', 'ملغي'])
            ->sum('amount');

        // حساب أرباح الفترة المحددة من جدول الأرباح
        $periodProfit = \App\Models\Profit::where('company_id', $companyId)
            ->whereBetween('profit_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('profit_amount');

        // حساب السيولة الصافية (Assets vs Liabilities) من الخزن النشطة الخاصة بالشركة والموظفين فقط
        $liquidityStats = DB::table('cash_boxes')
            ->where('company_id', $companyId)
            ->where('status', 'active')
             ->where(function ($q) use ($companyId) {
                  $q->whereNull('user_id')
                    ->orWhereExists(function ($sub) use ($companyId) {
                        $sub->select(DB::raw(1))
                            ->from('business_relations')
                            ->join('relation_types', 'relation_types.id', '=', 'business_relations.relation_type_id')
                            ->join('relation_type_capabilities', 'relation_type_capabilities.relation_type_id', '=', 'relation_types.id')
                            ->join('capabilities', 'capabilities.id', '=', 'relation_type_capabilities.capability_id')
                            ->whereColumn('business_relations.user_id', 'cash_boxes.user_id')
                            ->where('business_relations.company_id', $companyId)
                            ->where('capabilities.code', 'has_cash_custody');
                    });
             })
            ->selectRaw("
                SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_assets,
                SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END) as total_liabilities,
                SUM(balance) as net_liquidity
            ")
            ->first();

        $stats = [
            'total_sales' => (float) $totalSales,
            'monthly_sales' => (float) $periodSales,
            'pending_payments' => (float) Invoice::where('company_id', $companyId)
                ->where('remaining_amount', '>', 0)
                ->whereIn('status', ['confirmed', 'partial'])
                ->whereHas('invoiceType', function ($q) {
                    $q->where('code', 'sale');
                })
                ->sum('remaining_amount'),
            'supplier_debts' => (float) Invoice::where('company_id', $companyId)
                ->where('remaining_amount', '>', 0)
                ->whereIn('status', ['confirmed', 'partial'])
                ->whereHas('invoiceType', function ($q) {
                    $q->where('code', 'purchase');
                })
                ->sum('remaining_amount'),
            'unpaid_installments' => (float) Installment::where('company_id', $companyId)
                ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي', 'ملغى'])
                ->sum('remaining'),
            'total_customers' => \Modules\Companies\Models\BusinessRelation::where('company_id', $companyId)
                ->where('relation_type', 'customer')
                ->count(),
            'total_products' => Product::where('company_id', $companyId)->count(),
            'total_cash' => (float) ($liquidityStats->net_liquidity ?? 0),
            'monthly_expenses' => (float) $periodExpenses,
            'monthly_profit' => (float) $periodProfit,
            'liquidity' => [
                'total_assets' => (float) ($liquidityStats->total_assets ?? 0),
                'total_liabilities' => (float) abs($liquidityStats->total_liabilities ?? 0),
                'net_position' => (float) ($liquidityStats->net_liquidity ?? 0),
            ]
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
