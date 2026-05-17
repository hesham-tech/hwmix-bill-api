<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Resources\CashBoxTypeResource;
use App\Services\ImageService;
use Throwable;

/**
 * متحكم أنواع الخزن (CashBoxTypeController) - موديول المحاسبة
 */
class CashBoxTypeController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',
            'creator',
            'cashBoxes',
            'image',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = CashBoxType::with($this->relations)->withCount('cashBoxes');

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                $query->where('company_id', $authUser->company_id);
            } else {
                $query->where('created_by', $authUser->id);
            }

            $perPage = (int) $request->input('per_page', 20);
            $cashBoxTypes = $perPage == -1 ? $query->get() : $query->paginate(max(1, $perPage));

            return api_success(
                $perPage == -1 ? CashBoxTypeResource::collection($cashBoxTypes) : $cashBoxTypes->setCollection($cashBoxTypes->getCollection()->mapInto(CashBoxTypeResource::class)),
                $cashBoxTypes->isEmpty() ? 'لم يتم العثور على أنواع خزن.' : 'تم استرداد أنواع الخزن بنجاح.'
            );
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                ]);

                $validatedData['company_id'] = $authUser->company_id;
                $validatedData['created_by'] = $authUser->id;

                $cashBoxType = CashBoxType::create($validatedData);
                DB::commit();
                return api_success(new CashBoxTypeResource($cashBoxType->load($this->relations)), 'تم إنشاء نوع الخزنة بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $cashBoxType = CashBoxType::with($this->relations)->findOrFail($id);
            return api_success(new CashBoxTypeResource($cashBoxType), 'تم استرداد نوع الخزنة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $cashBoxType = CashBoxType::findOrFail($id);
            DB::beginTransaction();
            try {
                $cashBoxType->update($request->all());
                DB::commit();
                return api_success(new CashBoxTypeResource($cashBoxType->load($this->relations)), 'تم تحديث نوع الخزنة بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $ids = $request->input('item_ids');
            if (!$ids || !is_array($ids)) return api_error('معرفات غير صالحة.');

            DB::beginTransaction();
            try {
                CashBoxType::whereIn('id', $ids)->delete();
                DB::commit();
                return api_success(null, 'تم حذف أنواع الخزن بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function toggle(string $id): JsonResponse
    {
        try {
            $cashBoxType = CashBoxType::findOrFail($id);
            $cashBoxType->is_active = !$cashBoxType->is_active;
            $cashBoxType->save();
            return api_success(new CashBoxTypeResource($cashBoxType), 'تم تغيير الحالة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
