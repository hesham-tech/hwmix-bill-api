<?php
// وحدة التحكم الخاصة بجلب وتوفير الإحصائيات المباشرة للوحة تحكم كاش هونكس (HWNix Cash).

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\HwnixCash\Models\HwnixCashFinancialAccount;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessage;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;

class DashboardController extends Controller
{
    /**
     * جلب إحصائيات كاش هونكس المباشرة والخاصة بسياق الشركة الحالية.
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? request()->header('X-HWNIX-COMPANY');

        // إجمالي المحافظ والحسابات المالية الفعالة
        $totalAccounts = HwnixCashFinancialAccount::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->count();

        // إجمالي الخطوط المربوطة
        $activeLines = HwnixCashLine::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->count();

        // عدد الرسائل المستلمة اليوم
        $todayMessages = HwnixCashMessage::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereDate('created_at', now()->today())
            ->count();

        // عدد المعاملات المالية المنفذة اليوم
        $todayTransactions = HwnixCashWalletTransaction::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereDate('created_at', now()->today())
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'تم جلب الإحصائيات بنجاح.',
            'data' => [
                'totalAccounts' => $totalAccounts,
                'activeLines' => $activeLines,
                'todayMessages' => $todayMessages,
                'todayTransactions' => $todayTransactions,
            ],
        ]);
    }
}
