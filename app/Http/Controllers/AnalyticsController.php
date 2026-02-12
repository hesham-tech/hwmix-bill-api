<?php

namespace App\Http\Controllers;

use App\Models\StatsProductSummary;
use App\Models\StatsUserSummary;
use App\Models\StatsUserProductMatrix;
use App\Models\DailySalesSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * @group 09. الإحصائيات والتقارير
 * 
 * APIs لوحة التحكم والإحصائيات المتقدمة للمنتجات والعملاء.
 */
class AnalyticsController extends Controller
{
    /**
     * نظرة عامة على لوحة التحكم
     * 
     * جلب أرقام الأداء الرئيسية (إجمالي الإيرادات، الأرباح، عدد الطلبات) لليوم وللشهر الحالي.
     * 
     * @authenticated
     */
    public function dashboard(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $today = now()->toDateString();
        $month = now()->format('Y-m');

        // Today's snapshot
        $todayStats = DailySalesSummary::where('company_id', $companyId)
            ->where('date', $today)
            ->first();

        // Month-to-date calculation
        $monthStats = DailySalesSummary::where('company_id', $companyId)
            ->where('date', 'like', "$month-%")
            ->selectRaw('SUM(total_revenue) as revenue, COUNT(*) as orders_count')
            ->first();

        return api_success([
            'today' => [
                'revenue' => (float) ($todayStats->total_revenue ?? 0),
                'profit' => (float) ($todayStats->net_profit ?? 0),
                'orders_count' => (int) ($todayStats->sales_count ?? 0),
            ],
            'month_to_date' => [
                'revenue' => (float) ($monthStats->revenue ?? 0),
                'orders_count' => (int) ($monthStats->orders_count ?? 0),
            ]
        ]);
    }

    /**
     * المنتجات الأكثر مبيعاً
     * 
     * جلب قائمة بالمنتجات الأعلى أداءً بناءً على الكمية المباعة أو الأرباح المحققة.
     * 
     * @queryParam sort_by string الحقل المراد الترتيب بناءً عليه (total_sold_quantity, total_profit, total_revenue). Default: total_sold_quantity.
     * @queryParam limit int عدد النتائج المطلوبة. Default: 10.
     * 
     * @authenticated
     */
    public function topProducts(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $sortBy = $request->input('sort_by', 'total_sold_quantity');
        $limit = $request->input('limit', 10);

        $topStats = StatsProductSummary::where('company_id', $companyId)
            ->with(['product:id,name,sku'])
            ->orderBy($sortBy, 'desc')
            ->limit($limit)
            ->get();

        return api_success($topStats);
    }

    /**
     * تاريخ مشتريات مستخدم محدد
     * 
     * جلب تفاصيل كافة المنتجات التي اشتراها مستخدم معين وإجمالي إنفاقه على كل منها.
     * 
     * @urlParam user_id int required معرف المستخدم.
     * 
     * @authenticated
     */
    public function userHistory($userId)
    {
        $companyId = Auth::user()->company_id;

        $userSummary = StatsUserSummary::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        $productMatrix = StatsUserProductMatrix::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->with(['product:id,name'])
            ->orderBy('last_purchased_at', 'desc')
            ->get();

        return api_success([
            'summary' => $userSummary,
            'details' => $productMatrix
        ]);
    }
}
