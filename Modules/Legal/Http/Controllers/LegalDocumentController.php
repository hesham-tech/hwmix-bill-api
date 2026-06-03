<?php

namespace Modules\Legal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Legal\Http\Requests\StoreLegalDocumentRequest;
use Modules\Legal\Models\LegalDocument;
use Modules\Legal\Transformers\LegalDocumentResource;

/**
 * متحكم لإدارة المستندات القانونية (إنشاء وتعديل وإلغاء مسودات المستندات).
 */
class LegalDocumentController extends Controller
{


    /**
     * عرض قائمة المستندات المتوفرة (سواء العامة أو الخاصة بالشركة).
     */
    public function index(): JsonResponse
    {
        $this->checkPermission('legal_documents.view_all');

        $documents = LegalDocument::with(['activeVersion', 'versions'])
            ->withCount('versions')
            ->get();

        return api_success(LegalDocumentResource::collection($documents), 'تم جلب المستندات القانونية بنجاح.');
    }

    /**
     * إنشاء مستند قانوني جديد.
     */
    public function store(StoreLegalDocumentRequest $request): JsonResponse
    {
        $this->checkPermission('legal_documents.create');

        $validated = $request->validated();
        
        // تحديد الـ company_id: إذا كان المستخدم سوبر أدمن، يمكنه تمرير company_id = null (مستند عام)
        // أما إذا كان مدير شركة عادي، فيتم ربطه بالشركة الحالية تلقائياً.
        $user = Auth::user();
        if ($user->hasPermissionTo(perm_key('admin.super'))) {
            $validated['company_id'] = $request->input('company_id', null);
        } else {
            $validated['company_id'] = $user->active_company_id;
        }

        // منع التكرار لنفس الكود (key) في نفس الشركة
        $exists = LegalDocument::withoutGlobalScopes()
            ->where('key', $validated['key'])
            ->where('company_id', $validated['company_id'])
            ->exists();

        if ($exists) {
            return api_error('المستند بهذا المفتاح موجود بالفعل لهذه الشركة.', [], 422);
        }

        $document = LegalDocument::create($validated);

        return api_success(new LegalDocumentResource($document), 'تم إنشاء المستند القانوني بنجاح.', 201);
    }

    /**
     * عرض تفاصيل مستند محدد مع إصداراته.
     */
    public function show($id): JsonResponse
    {
        $this->checkPermission('legal_documents.view_all');

        $document = LegalDocument::with(['versions', 'activeVersion'])->findOrFail($id);
        
        $this->authorizeAccess($document);

        return api_success(new LegalDocumentResource($document), 'تم جلب المستند بنجاح.');
    }

    /**
     * تحديث بيانات المستند (تغيير حالته فقط، التعديل الفعلي للمحتوى يكون عبر الإصدارات).
     */
    public function update(StoreLegalDocumentRequest $request, $id): JsonResponse
    {
        $this->checkPermission('legal_documents.update_all');

        $document = LegalDocument::findOrFail($id);
        $this->authorizeAccess($document);

        $validated = $request->validated();
        
        // لا نسمح بتعديل المفتاح (key) بعد الإنشاء لمنع كسر مسارات الفرونت إند
        unset($validated['key']);

        $document->update($validated);

        return api_success(new LegalDocumentResource($document), 'تم تحديث بيانات المستند بنجاح.');
    }

    /**
     * حذف المستند بالكامل (Soft Delete).
     */
    public function destroy($id): JsonResponse
    {
        $this->checkPermission('legal_documents.delete_all');

        $document = LegalDocument::findOrFail($id);
        $this->authorizeAccess($document);

        $document->delete();

        return api_success(null, 'تم حذف المستند بنجاح.');
    }

    /**
     * دالة التحقق من الصلاحيات مع دعم perm_key التلقائي
     */
    protected function checkPermission(string $permission)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo(perm_key($permission)) && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }

    /**
     * التحقق من حق الوصول للمستند بناءً على الشركة
     */
    protected function authorizeAccess(LegalDocument $document)
    {
        $user = Auth::user();
        if ($document->company_id !== null && $document->company_id !== $user->active_company_id && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك بالوصول لهذا المستند.');
        }
    }
}
