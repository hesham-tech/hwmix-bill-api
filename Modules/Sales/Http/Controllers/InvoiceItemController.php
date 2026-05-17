<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Http\Requests\StoreInvoiceItemRequest;
use Modules\Sales\Http\Requests\UpdateInvoiceItemRequest;
use Modules\Sales\Http\Resources\InvoiceItemResource;
use Modules\Sales\Models\InvoiceItem;
use Modules\Sales\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceItemController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = ['invoice', 'product', 'company', 'creator'];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = InvoiceItem::query()->with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_items.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض عناصر الفواتير.');
            }

            if ($request->filled('invoice_id')) $query->where('invoice_id', $request->input('invoice_id'));
            if ($request->filled('product_id')) $query->where('product_id', $request->input('product_id'));

            $perPage = max(1, (int) $request->input('per_page', 20));
            $items = $query->orderBy($request->input('sort_by', 'id'), $request->input('sort_order', 'desc'))->paginate($perPage);

            return $items->isEmpty() 
                ? api_success([], 'لم يتم العثور على عناصر فواتير.')
                : api_success(InvoiceItemResource::collection($items), 'تم جلب عناصر الفواتير بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreInvoiceItemRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || !$authUser->company_id) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('invoice_items.create'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك صلاحية لإنشاء عناصر فواتير.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $authUser->company_id;

                Invoice::where('id', $validatedData['invoice_id'])->where('company_id', $authUser->company_id)->firstOrFail();

                $item = InvoiceItem::create($validatedData);
                $item->load($this->relations);
                DB::commit();
                return api_success(new InvoiceItemResource($item), 'تم إنشاء عنصر الفاتورة بنجاح.', 201);
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

            $item = InvoiceItem::with($this->relations)->findOrFail($id);
            $canView = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                      ($authUser->hasAnyPermission([perm_key('invoice_items.view_all'), perm_key('admin.company')]) && $item->belongsToCurrentCompany()) ||
                      ($authUser->hasPermissionTo(perm_key('invoice_items.view_children')) && $item->belongsToCurrentCompany() && $item->createdByUserOrChildren()) ||
                      ($authUser->hasPermissionTo(perm_key('invoice_items.view_self')) && $item->belongsToCurrentCompany() && $item->createdByCurrentUser());

            return $canView 
                ? api_success(new InvoiceItemResource($item), 'تم استرداد عنصر الفاتورة بنجاح.')
                : api_forbidden('ليس لديك إذن لعرض عنصر الفاتورة هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function update(UpdateInvoiceItemRequest $request, string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $item = InvoiceItem::findOrFail($id);
            $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('invoice_items.update_all'), perm_key('admin.company')]) && $item->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('invoice_items.update_children')) && $item->belongsToCurrentCompany() && $item->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('invoice_items.update_self')) && $item->belongsToCurrentCompany() && $item->createdByCurrentUser());

            if (!$canUpdate) return api_forbidden('ليس لديك إذن لتحديث عنصر الفاتورة هذا.');

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                if (isset($validatedData['invoice_id']) && $validatedData['invoice_id'] != $item->invoice_id) {
                    Invoice::where('id', $validatedData['invoice_id'])->where('company_id', $authUser->company_id)->firstOrFail();
                }

                $item->update($validatedData);
                $item->load($this->relations);
                DB::commit();
                return api_success(new InvoiceItemResource($item), 'تم تحديث عنصر الفاتورة بنجاح.');
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

            $item = InvoiceItem::findOrFail($id);
            $canDelete = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('invoice_items.delete_all'), perm_key('admin.company')]) && $item->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('invoice_items.delete_children')) && $item->belongsToCurrentCompany() && $item->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('invoice_items.delete_self')) && $item->belongsToCurrentCompany() && $item->createdByCurrentUser());

            if (!$canDelete) return api_forbidden('ليس لديك إذن لحذف عنصر الفاتورة هذا.');

            DB::beginTransaction();
            try {
                $deletedItem = $item->replicate();
                $item->delete();
                DB::commit();
                return api_success(new InvoiceItemResource($deletedItem), 'تم حذف عنصر الفاتورة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
