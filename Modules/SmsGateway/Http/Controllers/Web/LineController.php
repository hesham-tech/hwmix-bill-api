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
            ->where('company_id', $user->company_id)
            ->get();

        $formatted = $lines->map(fn($line) => [
            'id' => $line->id,
            'slot_index' => $line->slot_index,
            'carrier' => $line->carrier,
            'phone_number' => $line->phone_number,
            'network_type' => $line->network_type,
            'signal_strength' => $line->signal_strength,
            'status' => $line->status,
            'balance' => $line->balance,
            'actual_balance' => $line->actual_balance,
            'daily_limit' => $line->daily_limit,
            'note' => $line->note,
            'device' => [
                'id' => $line->device?->id,
                'device_name' => $line->device?->device_name,
                'status' => $line->device?->status,
            ]
        ]);

        return api_success($formatted, 'تم جلب قائمة الخطوط بنجاح.');
    }

    /**
     * تحديث بيانات شريحة الاتصال (الرصيد، الرصيد الفعلي، الليمت، الملاحظات).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('sms_gateway.edit_all')) && !$user->hasPermissionTo(perm_key('sms_gateway.edit_self'))) {
            return api_forbidden('غير مصرح لك بتعديل الخطوط.');
        }

        $line = SmsLine::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$line) {
            return api_error('الشريحة غير متوفرة أو لا تنتمي لشركتك.', [], 404);
        }

        $validated = $request->validate([
            'balance' => 'nullable|numeric|min:0',
            'actual_balance' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $line->update($validated);

        return api_success($line, 'تم تحديث بيانات الشريحة بنجاح.');
    }
}
