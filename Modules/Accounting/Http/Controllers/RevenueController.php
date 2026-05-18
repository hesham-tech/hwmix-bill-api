<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreRevenueRequest;
use Modules\Accounting\Http\Requests\UpdateRevenueRequest;
use Modules\Accounting\Http\Resources\RevenueResource;
use Modules\Accounting\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * متحكم الإيرادات (RevenueController) - موديول المحاسبة
 */
class RevenueController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',
            'customer',
            'creator',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Revenue::query()->with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.view_all'), perm_key('admin.company')])) {
                $query->where('company_id', $authUser->active_company_id);
            } else {
                $query->where('created_by', $authUser->id);
            }

            $perPage = max(1, (int) $request->get('per_page', 15));
            $revenues = $query->orderBy('id', 'desc')->paginate($perPage);

            return api_success(RevenueResource::collection($revenues), 'تم جلب الإيرادات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreRevenueRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('revenues.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء إيرادات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->active_company_id;

                $revenue = Revenue::create($validatedData);
                $revenue->load($this->relations);
                DB::commit();
                return api_success(new RevenueResource($revenue), 'تم إنشاء الإيراد بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function show(Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $revenue->company_id !== $authUser->active_company_id) {
                return api_forbidden();
            }

            return api_success(new RevenueResource($revenue->load($this->relations)), 'تم استرداد الإيراد بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateRevenueRequest $request, Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || ($revenue->company_id !== $authUser->active_company_id && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_forbidden();
            }

            DB::beginTransaction();
            try {
                $revenue->update($request->validated());
                DB::commit();
                return api_success(new RevenueResource($revenue->load($this->relations)), 'تم تحديث الإيراد بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function destroy(Revenue $revenue): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || ($revenue->company_id !== $authUser->active_company_id && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_forbidden();
            }

            $revenue->delete();
            return api_success(null, 'تم حذف الإيراد بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
