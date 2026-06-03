<?php

namespace Modules\Legal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Legal\Http\Requests\StoreVersionRequest;
use Modules\Legal\Models\LegalDocument;
use Modules\Legal\Models\LegalDocumentVersion;
use Modules\Legal\Transformers\LegalDocumentVersionResource;

/**
 * متحكم لإدارة وإصدار مسودات المستندات القانونية وإصداراتها ونشرها.
 */
class LegalVersionController extends Controller
{


    /**
     * إنشاء مسودة إصدار جديد لمستند قانوني معين.
     */
    public function store(StoreVersionRequest $request, $documentId): JsonResponse
    {
        $this->checkPermission('legal_documents.update_all');

        $document = LegalDocument::findOrFail($documentId);
        $this->authorizeAccess($document);

        $validated = $request->validated();
        
        // التحقق من عدم تكرار رقم الإصدار لنفس المستند
        $versionExists = LegalDocumentVersion::withoutGlobalScopes()
            ->where('legal_document_id', $documentId)
            ->where('version', $validated['version'])
            ->exists();

        if ($versionExists) {
            return api_error('هذا الإصدار موجود بالفعل لهذا المستند.', [], 422);
        }

        $validated['legal_document_id'] = $document->id;
        $validated['status'] = 'draft';
        $validated['company_id'] = $document->company_id;

        $version = LegalDocumentVersion::create($validated);

        return api_success(new LegalDocumentVersionResource($version), 'تم إنشاء مسودة الإصدار بنجاح.', 201);
    }

    /**
     * تحديث مسودة إصدار محددة (طالما لم تنشر بعد).
     */
    public function update(StoreVersionRequest $request, $id): JsonResponse
    {
        $this->checkPermission('legal_documents.update_all');

        $version = LegalDocumentVersion::findOrFail($id);
        $this->authorizeAccess($version);

        if ($version->status !== 'draft') {
            return api_error('لا يمكن تعديل الإصدار بعد نشره أو أرشفته.', [], 422);
        }

        $validated = $request->validated();

        // التحقق من عدم تكرار رقم الإصدار لنفس المستند في حال تم تعديله
        if ($validated['version'] !== $version->version) {
            $versionExists = LegalDocumentVersion::withoutGlobalScopes()
                ->where('legal_document_id', $version->legal_document_id)
                ->where('version', $validated['version'])
                ->where('id', '!=', $id)
                ->exists();

            if ($versionExists) {
                return api_error('رقم الإصدار المقترح موجود بالفعل لهذا المستند.', [], 422);
            }
        }

        $version->update($validated);

        return api_success(new LegalDocumentVersionResource($version), 'تم تحديث مسودة الإصدار بنجاح.');
    }

    /**
     * نشر إصدار جديد وأرشفة الإصدارات السابقة (تنفذ داخل Transaction).
     */
    public function publish($id): JsonResponse
    {
        $this->checkPermission('legal_documents.update_all');

        $version = LegalDocumentVersion::findOrFail($id);
        $this->authorizeAccess($version);

        if ($version->status !== 'draft') {
            return api_error('يمكن نشر مسودات الإصدارات فقط.', [], 422);
        }

        DB::transaction(function () use ($version) {
            // أرشفة جميع الإصدارات السابقة المنشورة لنفس المستند
            LegalDocumentVersion::where('legal_document_id', $version->legal_document_id)
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            // نشر الإصدار الحالي وتحديث تاريخ النشر
            $version->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        });

        // جلب البيانات المحدثة
        $version->refresh();

        return api_success(new LegalDocumentVersionResource($version), 'تم نشر الإصدار الجديد بنجاح وأرشفة الإصدارات السابقة.');
    }

    /**
     * حذف مسودة إصدار غير منشورة.
     */
    public function destroy($id): JsonResponse
    {
        $this->checkPermission('legal_documents.delete_all');

        $version = LegalDocumentVersion::findOrFail($id);
        $this->authorizeAccess($version);

        if ($version->status === 'published') {
            return api_error('لا يمكن حذف الإصدار النشط المنشور حالياً.', [], 422);
        }

        $version->delete();

        return api_success(null, 'تم حذف الإصدار بنجاح.');
    }

    /**
     * دالة التحقق من الصلاحيات
     */
    protected function checkPermission(string $permission)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo(perm_key($permission)) && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }

    /**
     * التحقق من حق الوصول
     */
    protected function authorizeAccess($model)
    {
        $user = Auth::user();
        if ($model->company_id !== null && $model->company_id !== $user->active_company_id && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك بالوصول لهذه البيانات.');
        }
    }
}
