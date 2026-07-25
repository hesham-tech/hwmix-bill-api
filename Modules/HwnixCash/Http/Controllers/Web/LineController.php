<?php
// متحكم لإدارة خطوط الاتصال والمحافظ الإلكترونية بكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HwnixCash\Http\Requests\Web\UpdateLineRequest;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Transformers\LineResource;

class LineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash.view_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash.view_self'))) {
            return api_forbidden('غير مصرح لك بعرض الخطوط.');
        }

        $lines = HwnixCashLine::with('device')
            ->where('company_id', $user->company_id)
            ->get();

        return api_success(LineResource::collection($lines), 'تم جلب قائمة الخطوط بنجاح.');
    }

    public function update(UpdateLineRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermissionTo(perm_key('admin.super')) && !$user->hasPermissionTo(perm_key('hwnix_cash.edit_all')) && !$user->hasPermissionTo(perm_key('hwnix_cash.edit_self'))) {
            return api_forbidden('غير مصرح لك بتعديل الخطوط.');
        }

        $line = HwnixCashLine::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$line) {
            return api_error('الخط غير متوفر أو لا ينتمي لشركتك.', [], 404);
        }

        $line->update($request->validated());

        return api_success(new LineResource($line), 'تم تحديث بيانات الخط بنجاح.');
    }
}
