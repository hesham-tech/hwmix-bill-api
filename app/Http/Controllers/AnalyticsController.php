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
 * @group 09. Ø§Ù„Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª ÙˆØ§Ù„ØªÙ‚Ø§Ø±ÙŠØ±
 * 
 * APIs Ù„ÙˆØ­Ø© Ø§Ù„ØªØ­ÙƒÙ… ÙˆØ§Ù„Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª Ø§Ù„Ù…ØªÙ‚Ø¯Ù…Ø© Ù„Ù„Ù…Ù†ØªØ¬Ø§Øª ÙˆØ§Ù„Ø¹Ù…Ù„Ø§Ø¡.
 */
class AnalyticsController extends Controller
{
    /**
     * Ù†Ø¸Ø±Ø© Ø¹Ø§Ù…Ø© Ø¹Ù„Ù‰ Ù„ÙˆØ­Ø© Ø§Ù„ØªØ­ÙƒÙ…
     * 
     * Ø¬Ù„Ø¨ Ø£Ø±Ù‚Ø§Ù… Ø§Ù„Ø£Ø¯Ø§Ø¡ Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ© (Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§ØªØŒ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ØŒ Ø¹Ø¯Ø¯ Ø§Ù„Ø·Ù„Ø¨Ø§Øª) Ù„Ù„ÙŠÙˆÙ… ÙˆÙ„Ù„Ø´Ù‡Ø± Ø§Ù„Ø­Ø§Ù„ÙŠ.
     * 
     * @authenticated
     */
    public function dashboard(Request $request)
    {
        $companyId = Auth::user()->active_company_id;
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
     * Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø£ÙƒØ«Ø± Ù…Ø¨ÙŠØ¹Ø§Ù‹
     * 
     * Ø¬Ù„Ø¨ Ù‚Ø§Ø¦Ù…Ø© Ø¨Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø£Ø¹Ù„Ù‰ Ø£Ø¯Ø§Ø¡Ù‹ Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø§Ù„ÙƒÙ…ÙŠØ© Ø§Ù„Ù…Ø¨Ø§Ø¹Ø© Ø£Ùˆ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„Ù…Ø­Ù‚Ù‚Ø©.
     * 
     * @queryParam sort_by string Ø§Ù„Ø­Ù‚Ù„ Ø§Ù„Ù…Ø±Ø§Ø¯ Ø§Ù„ØªØ±ØªÙŠØ¨ Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„ÙŠÙ‡ (total_sold_quantity, total_profit, total_revenue). Default: total_sold_quantity.
     * @queryParam limit int Ø¹Ø¯Ø¯ Ø§Ù„Ù†ØªØ§Ø¦Ø¬ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©. Default: 10.
     * 
     * @authenticated
     */
    public function topProducts(Request $request)
    {
        $companyId = Auth::user()->active_company_id;
        $sortBy = $request->input('sort_by', 'total_sold_quantity');
        $limit = $request->input('limit', 10);

        $topStats = StatsProductSummary::where('company_id', $companyId)
            ->with(['product:id,name'])
            ->orderBy($sortBy, 'desc')
            ->limit($limit)
            ->get();

        return api_success($topStats);
    }

    /**
     * ØªØ§Ø±ÙŠØ® Ù…Ø´ØªØ±ÙŠØ§Øª Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø­Ø¯Ø¯
     * 
     * Ø¬Ù„Ø¨ ØªÙØ§ØµÙŠÙ„ ÙƒØ§ÙØ© Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø§Ø´ØªØ±Ø§Ù‡Ø§ Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø¹ÙŠÙ† ÙˆØ¥Ø¬Ù…Ø§Ù„ÙŠ Ø¥Ù†ÙØ§Ù‚Ù‡ Ø¹Ù„Ù‰ ÙƒÙ„ Ù…Ù†Ù‡Ø§.
     * 
     * @urlParam user_id int required Ù…Ø¹Ø±Ù Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù….
     * 
     * @authenticated
     */
    public function userHistory($userId)
    {
        $companyId = Auth::user()->active_company_id;

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
