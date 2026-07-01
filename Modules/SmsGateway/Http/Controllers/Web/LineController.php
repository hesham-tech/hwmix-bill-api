<?php
// متحكم لإدارة شرائح الاتصال المتاحة وعرض حالات الإشارة لخطوط المبيعات.

namespace Modules\SmsGateway\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SmsGateway\Models\SmsLine;

class LineController extends Controller
{
    /**
     * عرض جميع خطوط الاتصال التابعة للشركة النشطة.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_gateway.view_all')) && !$user->hasPermissionTo(perm_key('sms_gateway.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض الخطوط.');
        }

        // استخدام Eager Loading لتحسين الأداء ومنع N+1 queries
        $lines = SmsLine::with('device')
            ->where('company_id', $user->active_company_id)
            ->get();

        $formatted = $lines->map(fn($line) => [
            'id' => $line->id,
            'slot_index' => $line->slot_index,
            'carrier' => $line->carrier,
            'phone_number' => $line->phone_number,
            'network_type' => $line->network_type,
            'signal_strength' => $line->signal_strength,
            'status' => $line->status,
            'device' => [
                'id' => $line->device?->id,
                'device_name' => $line->device?->device_name,
                'status' => $line->device?->status,
            ]
        ]);

        return api_success($formatted, 'تم جلب قائمة الخطوط بنجاح.');
    }
}
