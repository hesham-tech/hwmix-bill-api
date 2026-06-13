<?php
// متحكم استرجاع وحدات القياس للنظام والشركات
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Resources\UnitResource;
use Modules\Inventory\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class UnitController extends Controller
{
    /**
     * عرض قائمة وحدات القياس المتاحة
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $units = Unit::with('group')
                ->where('is_active', true)
                ->get();

            return api_success(UnitResource::collection($units), 'تم استرداد وحدات القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
