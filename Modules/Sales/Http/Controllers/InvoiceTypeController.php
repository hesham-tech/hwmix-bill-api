<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Http\Requests\StoreInvoiceTypeRequest;
use Modules\Sales\Http\Requests\UpdateInvoiceTypeRequest;
use Modules\Sales\Http\Resources\InvoiceTypeResource;
use Modules\Sales\Models\InvoiceType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use Throwable;

class InvoiceTypeController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = ['invoices', 'company', 'creator'];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || !$authUser->company_id) return api_unauthorized('يتطلب المصادقة.');

            $company = Company::find($authUser->company_id);
            $types = $company->invoiceTypes()
                ->when($request->filled('context'), fn($q) => $q->where('context', $request->input('context')))
                ->get();

            $types = $types->map(function ($type) {
                $type->is_active = (bool) $type->pivot->is_active;
                unset($type->pivot);
                return $type;
            });

            return $types->isEmpty() 
                ? api_success([], 'لم يتم العثور على أنواع فواتير.')
                : api_success(InvoiceTypeResource::collection($types), 'تم جلب أنواع الفواتير بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreInvoiceTypeRequest $request): JsonResponse
    {
        return api_error('لا يمكن إنشاء أنواع فواتير جديدة. أنواع الفواتير محمية ومُعرّفة مسبقاً في النظام.', [], 403);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || !$authUser->company_id) return api_unauthorized('يتطلب المصادقة.');

            $type = InvoiceType::with($this->relations)->findOrFail($id);
            $canView = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                      ($authUser->hasAnyPermission([perm_key('invoice_types.view_all'), perm_key('admin.company')]) && $type->belongsToCurrentCompany()) ||
                      ($authUser->hasPermissionTo(perm_key('invoice_types.view_children')) && $type->belongsToCurrentCompany() && $type->createdByUserOrChildren()) ||
                      ($authUser->hasPermissionTo(perm_key('invoice_types.view_self')) && $type->belongsToCurrentCompany() && $type->createdByCurrentUser());

            return $canView 
                ? api_success(new InvoiceTypeResource($type), 'تم استرداد نوع الفاتورة بنجاح.')
                : api_forbidden('ليس لديك إذن لعرض نوع الفاتورة هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateInvoiceTypeRequest $request, string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || !$authUser->company_id) return api_unauthorized('يتطلب المصادقة.');

            $type = InvoiceType::findOrFail($id);
            $company = Company::find($authUser->company_id);

            if (!$company->invoiceTypes()->where('invoice_type_id', $id)->exists()) {
                return api_error('هذا النوع غير مرتبط بشركتك.', [], 404);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                if (!isset($validatedData['is_active'])) {
                    DB::rollBack();
                    return api_error('يمكن فقط تفعيل أو تعطيل أنواع الفواتير.', [], 403);
                }

                $company->invoiceTypes()->updateExistingPivot($id, ['is_active' => $validatedData['is_active']]);
                DB::commit();

                $updatedType = $company->invoiceTypes()->find($id);
                $updatedType->is_active = (bool) $updatedType->pivot->is_active;
                unset($updatedType->pivot);

                return api_success(new InvoiceTypeResource($updatedType), $validatedData['is_active'] ? 'تم تفعيل نوع الفاتورة بنجاح.' : 'تم تعطيل نوع الفاتورة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        return api_error('لا يمكن حذف أنواع الفواتير. يمكنك فقط تعطيلها باستخدام خاصية التفعيل/التعطيل.', [], 403);
    }
}
