<?php

namespace Modules\Sales\Http\Controllers;

use Throwable;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceType;
use Illuminate\Http\Request;
use Modules\Sales\Services\ServiceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Http\Resources\InvoiceResource;
use Modules\Sales\Http\Requests\StoreInvoiceRequest;
use Modules\Sales\Http\Requests\UpdateInvoiceRequest;
use App\Services\PDFService;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    private array $indexRelations;
    private array $showRelations;

    public function __construct()
    {
        $this->indexRelations = ['customer', 'invoiceType', 'company', 'creator'];
        $this->showRelations = [
            'customer', 'company', 'invoiceType', 'items.variant', 
            'items.digitalDeliveries', 'installmentPlan', 'creator', 
            'payments.paymentMethod', 'payments.cashBox'
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $query = Invoice::query()->with($this->indexRelations);

            if ($request->boolean('due_only')) {
                $query->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                    ->where('status', '!=', Invoice::STATUS_CANCELED)
                    ->where('remaining_amount', '>', 0);
            }

            if ($request->filled('search')) {
                $query->smartSearch($request->input('search'), ['invoice_number'], [
                    'customer' => ['full_name', 'nickname', 'phone']
                ]);
            }

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // سوبر أدمن: إذا اختار شركة نشطة يرى فواتيرها فقط، وإلا يرى الكل
                $activeCompanyId = $authUser->active_company_id;
                if ($activeCompanyId) {
                    $query->where('company_id', $activeCompanyId);
                }
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                $query->where('user_id', $authUser->id);
            }

            if ($request->filled('invoice_type_id')) $query->where('invoice_type_id', $request->input('invoice_type_id'));
            if ($request->filled('user_id')) $query->where('user_id', $request->input('user_id'));
            
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            } else {
                $query->where('status', '!=', 'canceled');
            }

            if ($request->filled('payment_status')) {
                $pStatus = $request->input('payment_status');
                if (is_string($pStatus) && str_contains($pStatus, ',')) $pStatus = explode(',', $pStatus);
                is_array($pStatus) ? $query->whereIn('payment_status', $pStatus) : $query->where('payment_status', $pStatus);
            }

            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $invoices = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($request->filled('search') && $invoices->isNotEmpty()) {
                $refinedCollection = (new Invoice())->refineSimilarity(
                    collect($invoices->items()),
                    $request->input('search'),
                    ['invoice_number', 'customer.full_name', 'customer.nickname', 'customer.phone'],
                    80
                );
                $invoices->setCollection($refinedCollection);
            }

            return $invoices->isEmpty() 
                ? api_success([], 'لم يتم العثور على فواتير.')
                : api_success(InvoiceResource::collection($invoices), 'تم جلب الفواتير بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $invoice = Invoice::with($this->showRelations)->findOrFail($id);
            $authUser = Auth::user();
            
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if ($invoice->company_id !== $authUser->active_company_id && $invoice->user_id !== $authUser->id) {
                    return api_forbidden('ليس لديك صلاحية للوصول لهذه الفاتورة.');
                }
            }

            return api_success(new InvoiceResource($invoice), 'تم جلب بيانات الفاتورة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;
            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو اختيار شركة نشطة. يرجى تسجيل الخروج وإعادة الدخول.');
            }

            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('invoices.create'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك صلاحية لإنشاء الفواتير.');
            }

            $validated = $request->validated();
            $validated['company_id'] = $companyId;
            $validated['created_by'] = $authUser->id;

            DB::beginTransaction();
            try {
                $invoiceType = InvoiceType::findOrFail($validated['invoice_type_id']);
                $invoiceTypeCode = $validated['invoice_type_code'] ?? $invoiceType->code;

                $service = ServiceResolver::resolve($invoiceTypeCode);
                $responseDTO = $service->create($validated);

                $responseDTO->load($this->showRelations);
                DB::commit();
                return api_success(new InvoiceResource($responseDTO), 'تم إنشاء المستند بنجاح', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                // إرجاع رسالة الخطأ الفعلية للمستخدم بدلاً من إخفائها
                return api_exception($e, 500, 'فشل إنشاء الفاتورة: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            return api_exception($e, 500, 'حدث خطأ غير متوقع: ' . $e->getMessage());
        }
    }


    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('invoices.update_all'), perm_key('admin.company')]) && $invoice->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('invoices.update_children')) && $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('invoices.update_self')) && $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser());

            if (!$canUpdate) return api_forbidden('ليس لديك صلاحية لتعديل هذه الفاتورة.');

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $companyId = $authUser->active_company_id;
                $validated['company_id'] = $companyId;
                $validated['updated_by'] = $authUser->id;

                $service = ServiceResolver::resolve($invoice->invoice_type_code);
                $updatedInvoice = $service->update($validated, $invoice);
                $updatedInvoice->load($this->showRelations);

                DB::commit();
                return api_success(new InvoiceResource($updatedInvoice), 'تم تعديل الفاتورة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('خطأ في التحقق من البيانات', $e->errors(), 422);
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
        DB::beginTransaction();
        try {
            $authUser = Auth::user();
            if (!$authUser || !$authUser->active_company_id) return api_unauthorized('يتطلب المصادقة.');

            $invoice = Invoice::findOrFail($id);
            $canDelete = $authUser->hasPermissionTo(perm_key('admin.super')) || 
                        ($authUser->hasAnyPermission([perm_key('invoices.delete_all'), perm_key('admin.company')]) && $invoice->belongsToCurrentCompany()) ||
                        ($authUser->hasPermissionTo(perm_key('invoices.delete_children')) && $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren()) ||
                        ($authUser->hasPermissionTo(perm_key('invoices.delete_self')) && $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser());

            if (!$canDelete) return api_forbidden('ليس لديك صلاحية لحذف هذه الفاتورة.');

            $service = ServiceResolver::resolve($invoice->invoice_type_code);
            $canceledInvoice = $service->cancel($invoice);

            DB::commit();
            return api_success(new InvoiceResource($canceledInvoice), 'تم حذف الفاتورة بنجاح');
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }

    public function downloadPDF($id)
    {
        try {
            $invoice = Invoice::with(['items.product', 'items.variant', 'customer', 'company', 'invoiceType', 'payments'])->findOrFail($id);
            return app(PDFService::class)->generateInvoicePDF($invoice);
        } catch (\Exception $e) {
            return response()->json(['error' => 'فشل في إنشاء PDF'], 500);
        }
    }
}
