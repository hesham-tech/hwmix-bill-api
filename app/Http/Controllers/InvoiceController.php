<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Illuminate\Http\Request;
use App\Services\ServiceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Services\PDFService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class InvoiceController extends Controller
{
    /**
     * @group 02. إدارة الفواتير
     * 
     * تحميل الفاتورة كـ PDF
     * 
     * @urlParam id required معرف الفاتورة. Example: 1
     */
    public function downloadPDF($id)
    {
        try {
            $invoice = Invoice::with(['items.product', 'items.variant', 'customer', 'company', 'invoiceType', 'payments'])
                ->findOrFail($id);

            return app(PDFService::class)->generateInvoicePDF($invoice);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF: ' . $e->getMessage());
            return response()->json(['error' => 'فشل في إنشاء PDF'], 500);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * بيانات الفاتورة للواجهة الأمامية (PDF)
     * 
     * @urlParam id required معرف الفاتورة. Example: 1
     */
    public function getInvoiceForPDF($id)
    {
        try {
            $invoice = Invoice::with(['items.product', 'items.variant', 'customer', 'company', 'invoiceType', 'payments'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\Invoice\InvoiceForPDFResource($invoice),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get invoice data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'فشل في جلب بيانات الفاتورة'
            ], 500);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * إرسال الفاتورة عبر البريد
     * 
     * @urlParam id required معرف الفاتورة. Example: 1
     * @bodyParam recipients string[] قائمة البريد الإلكتروني. Example: ["client@example.com"]
     * @bodyParam subject string موضوع الرسالة. Example: فاتورة مبيعات #123
     */
    public function emailPDF($id, Request $request)
    {
        try {
            $invoice = Invoice::with(['items.product', 'customer', 'company'])->findOrFail($id);

            $recipients = $request->input('recipients', [$invoice->user->email]);
            $subject = $request->input('subject');

            $success = app(PDFService::class)->emailInvoicePDF($invoice, $recipients, $subject);

            if ($success) {
                return response()->json(['message' => 'تم إرسال الفاتورة بالبريد الإلكتروني']);
            }

            return response()->json(['error' => 'فشل في إرسال البريد'], 500);
        } catch (\Exception $e) {
            Log::error('Failed to email invoice PDF: ' . $e->getMessage());
            return response()->json(['error' => 'فشل في إرسال الفاتورة'], 500);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * تصدير الفواتير إلى Excel
     * 
     * @bodyParam ids integer[] قائمة المعرفات المطلوب تصديرها. Example: [1, 2, 3]
     */
    public function exportExcel(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'nullable|array',
                'ids.*' => 'exists:invoices,id',
            ]);

            $query = Invoice::with(['items', 'customer', 'invoiceType', 'company']);

            if ($request->filled('ids')) {
                $query->whereIn('id', $request->ids);
            } else {
                // Export all (with limit for safety)
                $query->limit(1000);
            }

            $invoices = $query->get();

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\InvoicesExport($invoices),
                'invoices_' . now()->format('Y-m-d') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('Failed to export invoices: ' . $e->getMessage());
            return response()->json(['error' => 'فشل في تصدير الفواتير'], 500);
        }
    }
    private array $indexRelations;
    private array $showRelations;

    public function __construct()
    {
        $this->indexRelations = [
            'customer.cashBoxDefault',
            'invoiceType',
            'company',
            'creator',
        ];

        $this->showRelations = [
            'customer.cashBoxDefault',
            'company',
            'invoiceType',
            'items.variant',
            'items.digitalDeliveries',
            'installmentPlan',
            'creator',
            'payments.paymentMethod',
            'payments.cashBox',
        ];
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * عرض قائمة الفواتير
     * 
     * استرجاع قائمة بجميع الفواتير مع إمكانية التصفية المتقدمة حسب النوع، الحالة، العميل، أو التاريخ.
     * 
     * @queryParam type string نوع الفاتورة (sale, purchase, return_sale, return_purchase). Example: sale
     * @queryParam user_id integer فلترة حسب العميل أو المورد.
     * @queryParam status string حالة الفاتورة (draft, confirmed, paid, partially_paid, cancelled). Example: confirmed
     * @queryParam date_from date تاريخ البداية.
     * @queryParam date_to date تاريخ النهاية.
     * @queryParam per_page integer عدد النتائج في الصفحة. Example: 15
     * 
     * @apiResourceCollection App\Http\Resources\Invoice\InvoiceResource
     * @apiResourceModel App\Models\Invoice
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Invoice::query()->with($this->indexRelations);

            // فلترة الفواتير المستحقة فقط (غير مدفوعة أو مدفوعة جزئياً)
            if ($request->boolean('due_only')) {
                $query->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                    ->where('status', '!=', Invoice::STATUS_CANCELED)
                    ->where('remaining_amount', '>', 0);
            }

            // البحث (رقم الفاتورة أو اسم العميل)
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($qu) use ($search) {
                            $qu->where('full_name', 'like', "%{$search}%")
                                ->orWhere('nickname', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            }
            // إضافة صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // لا قيود
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // الوضع الافتراضي للعملاء: رؤية الفواتير الخاصة بهم فقط
                $query->where('user_id', $authUser->id);
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('invoice_type_id')) {
                $query->where('invoice_type_id', $request->input('invoice_type_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            // فلتر الحالة: استثناء الملغاة افتراضياً ما لم يتم طلبها صراحةً
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            } else {
                $query->where('status', '!=', 'canceled');
            }

            // فلاتر التاريخ
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $invoices = $query->orderByRaw('GREATEST(updated_at, created_at) DESC')->paginate($perPage);

            if ($invoices->isEmpty()) {
                return api_success([], 'لم يتم العثور على فواتير.');
            } else {
                return api_success(InvoiceResource::Collection($invoices), 'تم جلب الفواتير بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * عرض تفاصيل فاتورة
     * 
     * استرجاع كافة تفاصيل الفاتورة بما في ذلك الأصناف، العميل، والشركة.
     * 
     * @urlParam id required معرف الفاتورة. Example: 1
     * 
     * @apiResource App\Http\Resources\Invoice\InvoiceResource
     * @apiResourceModel App\Models\Invoice
     */
    public function show($id): JsonResponse
    {
        try {
            $invoice = Invoice::with($this->showRelations)->findOrFail($id);

            // التحقق من صلاحية الوصول
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if ($invoice->company_id !== $authUser->company_id && $invoice->user_id !== $authUser->id) {
                    Log::warning('Unauthorized access attempt to invoice details', [
                        'user_id' => $authUser->id,
                        'invoice_id' => $id,
                        'company_id' => $invoice->company_id,
                        'user_company_id' => $authUser->company_id
                    ]);
                    return api_forbidden('ليس لديك صلاحية للوصول لهذه الفاتورة.');
                }
            }

            return api_success(new InvoiceResource($invoice), 'تم جلب بيانات الفاتورة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * إنشاء فاتورة جديدة
     * 
     * يدعم إنشاء فواتير البيع والشراء والخدمات مع معالجة المخزون والحسابات تلقائياً.
     * 
     * @bodyParam invoice_type_id integer required معرف النوع. Example: 1
     * @bodyParam user_id integer required معرف العميل/المورد. Example: 2
     * @bodyParam items array required مصفوفة المنتجات.
     * @bodyParam items.*.product_id integer required معرف المنتج.
     * @bodyParam items.*.quantity number required الكمية. Example: 5
     * @bodyParam items.*.unit_price number required سعر الوحدة. Example: 150
     * @bodyParam paid_amount number required المبلغ المدفوع حالياً. Example: 750
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // إضافة صلاحية الإنشاء
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('invoices.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                Log::warning('Unauthorized attempt to create invoice', [
                    'user_id' => $authUser->id,
                    'company_id' => $companyId
                ]);
                return api_forbidden('ليس لديك صلاحية لإنشاء الفواتير.');
            }

            $validated = $request->validated();
            $validated['company_id'] = $companyId;
            $validated['created_by'] = $authUser->id;

            DB::beginTransaction();
            try {
                $invoiceType = InvoiceType::findOrFail($validated['invoice_type_id']);
                $invoiceTypeCode = $validated['invoice_type_code'] ?? $invoiceType->code;

                $serviceResolver = new ServiceResolver();
                $service = $serviceResolver->resolve($invoiceTypeCode);

                $responseDTO = $service->create($validated);

                if (!$responseDTO || !$responseDTO instanceof Invoice) {
                    Log::error('لم يتم إنشاء الفاتورة بنجاح من الـ Service', [
                        'returned_value' => $responseDTO,
                        'invoice_type_code' => $invoiceTypeCode,
                        'validated_data' => $validated,
                    ]);
                    throw new \Exception('فشل إنشاء الفاتورة من الخدمة.');
                }

                $responseDTO->load($this->showRelations);
                DB::commit();
                return api_success(new InvoiceResource($responseDTO), 'تم إنشاء المستند بنجاح', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء إنشاء المستند.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 02. إدارة الفواتير
     * 
     * تحديث بيانات فاتورة
     * 
     * @urlParam invoice required معرف الفاتورة (Model Binding). Example: 1
     * @bodyParam user_id integer معرف العميل المحدث.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الانتماء لشركة.');
            }

            $canUpdate = false;
            // إضافة صلاحيات التعديل
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.update_all'), perm_key('admin.company')])) {
                $canUpdate = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_children'))) {
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_self'))) {
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            }

            if (!$canUpdate) {
                Log::warning('Unauthorized attempt to update invoice', [
                    'user_id' => $authUser->id,
                    'invoice_id' => $invoice->id,
                    'company_id' => $companyId
                ]);
                return api_forbidden('ليس لديك صلاحية لتعديل هذه الفاتورة.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $validated['company_id'] = $companyId;
                $validated['updated_by'] = $authUser->id;

                $invoiceTypeCode = $invoice->invoice_type_code;
                $service = ServiceResolver::resolve($invoiceTypeCode);

                $updatedInvoice = $service->update($validated, $invoice);
                $updatedInvoice->load($this->showRelations);

                DB::commit();
                return api_success(new InvoiceResource($updatedInvoice), 'تم تعديل الفاتورة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('خطأ في التحقق من البيانات', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }


    /**
     * @group 02. إدارة الفواتير
     * 
     * إلغاء/حذف فاتورة
     * 
     * @urlParam id required معرف الفاتورة. Example: 1
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction(); // بدء المعاملة لضمان اتساق البيانات
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $invoice = Invoice::with(['company', 'creator'])->findOrFail($id);

            // صلاحيات الحذف (يمكن تبسيطها أكثر داخل سياسات Laravel)
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.delete_all'), perm_key('admin.company')])) {
                $canDelete = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_children'))) {
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_self'))) {
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            }

            if (!$canDelete) {
                Log::warning('Unauthorized attempt to delete invoice', [
                    'user_id' => $authUser->id,
                    'invoice_id' => $id,
                    'company_id' => $companyId
                ]);
                return api_forbidden('ليس لديك صلاحية لحذف هذه الفاتورة.');
            }

            $invoiceTypeCode = $invoice->invoice_type_code; // الحصول على نوع الفاتورة
            $service = ServiceResolver::resolve($invoiceTypeCode); // حل الخدمة المناسبة لنوع الفاتورة

            $canceledInvoice = $service->cancel($invoice); // تفويض الحذف للخدمة

            DB::commit(); // تأكيد المعاملة
            return api_success(new InvoiceResource($canceledInvoice), 'تم حذف الفاتورة بنجاح');
        } catch (Throwable $e) {
            DB::rollBack(); // التراجع عن المعاملة في حالة حدوث أي خطأ
            return api_exception($e);
        }
    }
}
