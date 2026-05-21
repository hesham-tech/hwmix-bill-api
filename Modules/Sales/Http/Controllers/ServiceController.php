<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Http\Requests\StoreServiceRequest;
use Modules\Sales\Http\Requests\UpdateServiceRequest;
use Modules\Sales\Http\Resources\ServiceResource;
use Modules\Sales\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ServiceController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = ['company', 'creator'];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Service::query()->with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('services.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الخدمات.');
            }

            if ($request->filled('name')) $query->where('name', 'like', '%' . $request->input('name') . '%');
            
            $perPage = max(1, (int) $request->get('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $services = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return $services->isEmpty() 
                ? api_success([], 'لم يتم العثور على خدمات.')
                : api_success(ServiceResource::collection($services), 'تم جلب الخدمات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة أو اختيار شركة نشطة.');

            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('services.create'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك إذن لإنشاء خدمات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id'])) ? $validatedData['company_id'] : $companyId;

                $service = Service::create($validatedData);
                $service->load($this->relations);
                DB::commit();
                return api_success(new ServiceResource($service), 'تم إنشاء الخدمة بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $service = Service::with($this->relations)->findOrFail($id);
            $canView = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                      ($authUser->hasAnyPermission([perm_key('services.view_all'), perm_key('admin.company')]) && $service->belongsToCurrentCompany()) ||
                      ($authUser->hasPermissionTo(perm_key('services.view_children')) && $service->belongsToCurrentCompany() && $service->createdByUserOrChildren()) ||
                      ($authUser->hasPermissionTo(perm_key('services.view_self')) && $service->belongsToCurrentCompany() && $service->createdByCurrentUser());

            return $canView 
                ? api_success(new ServiceResource($service), 'تم استرداد الخدمة بنجاح.')
                : api_forbidden('ليس لديك إذن لعرض هذه الخدمة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateServiceRequest $request, string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $service = Service::findOrFail($id);
            $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('services.update_all'), perm_key('admin.company')]) && $service->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('services.update_children')) && $service->belongsToCurrentCompany() && $service->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('services.update_self')) && $service->belongsToCurrentCompany() && $service->createdByCurrentUser());

            if (!$canUpdate) return api_forbidden('ليس لديك إذن لتحديث هذه الخدمة.');

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                if (isset($validatedData['company_id']) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    unset($validatedData['company_id']);
                }

                $service->update($validatedData);
                $service->load($this->relations);
                DB::commit();
                return api_success(new ServiceResource($service), 'تم تحديث الخدمة بنجاح.');
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
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $service = Service::findOrFail($id);
            $canDelete = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('services.delete_all'), perm_key('admin.company')]) && $service->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('services.delete_children')) && $service->belongsToCurrentCompany() && $service->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('services.delete_self')) && $service->belongsToCurrentCompany() && $service->createdByCurrentUser());

            if (!$canDelete) return api_forbidden('ليس لديك إذن لحذف هذه الخدمة.');

            DB::beginTransaction();
            try {
                $deletedService = $service->replicate();
                $service->delete();
                DB::commit();
                return api_success(new ServiceResource($deletedService), 'تم حذف الخدمة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
