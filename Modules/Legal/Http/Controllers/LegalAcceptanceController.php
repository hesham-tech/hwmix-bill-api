<?php

namespace Modules\Legal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Legal\Http\Requests\AcceptDocumentRequest;
use Modules\Legal\Models\LegalDocument;
use Modules\Legal\Models\LegalDocumentAcceptance;
use Modules\Legal\Models\LegalDocumentVersion;
use Modules\Legal\Transformers\LegalDocumentAcceptanceResource;
use Modules\Legal\Transformers\LegalDocumentVersionResource;

/**
 * متحكم للتحقق من موافقات المستخدمين وتسجيل قبولهم التاريخي للمستندات والسياسات.
 */
class LegalAcceptanceController extends Controller
{


    /**
     * جلب المستند القانوني النشط للضيوف عن طريق الكود (الخصوصية، شروط الاستخدام...).
     */
    public function getActiveDocumentByKey(string $key, Request $request): JsonResponse
    {
        $companyId = $request->input('company_id', null);

        $query = LegalDocument::withoutGlobalScopes()
            ->where('key', $key)
            ->where('is_active', true);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        $document = $query->first();

        if (!$document) {
            return api_error('المستند غير موجود أو غير نشط.', [], 404);
        }

        $activeVersion = $document->activeVersion;

        if (!$activeVersion) {
            return api_error('لا يوجد إصدار منشور نشط لهذا المستند حالياً.', [], 404);
        }

        return api_success(new LegalDocumentVersionResource($activeVersion), 'تم جلب المستند بنجاح.');
    }

    /**
     * جلب قائمة بالمستندات النشطة المتوفرة للنظام (بدون محتوى كبير للتسريع).
     */
    public function getActiveDocumentsList(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id', null);

        $query = LegalDocument::withoutGlobalScopes()
            ->where('is_active', true);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        $documents = $query->with(['activeVersion'])->get();

        return api_success(LegalDocumentVersionResource::collection($documents->pluck('activeVersion')->filter()), 'تم جلب المستندات النشطة.');
    }

    /**
     * التحقق من وجود شروط أو وثائق لم يوافق عليها المستخدم الحالي بعد.
     */
    public function checkPending(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return api_success([], 'غير مسجل الدخول.');
        }

        // 1. جلب جميع المستندات النشطة الخاصة بنطاق المستخدم (العامة + شركته)
        $activeDocVersions = LegalDocumentVersion::whereHas('document', function ($query) {
                $query->where('is_active', true);
            })
            ->where('status', 'published')
            ->get();

        // 2. جلب معرفات الإصدارات التي وافق عليها المستخدم مسبقاً
        $acceptedVersionIds = LegalDocumentAcceptance::where('user_id', $user->id)
            ->pluck('legal_document_version_id')
            ->toArray();

        // 3. فلترة المستندات التي تحتاج إلى موافقة
        $pendingVersions = $activeDocVersions->filter(function ($version) use ($acceptedVersionIds) {
            return !in_array($version->id, $acceptedVersionIds);
        })->values();

        return api_success(LegalDocumentVersionResource::collection($pendingVersions), 'تم فحص المستندات المعلقة.');
    }

    /**
     * تسجيل موافقة المستخدم الحالي على إصدار مستند قانوني.
     */
    public function accept(AcceptDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        // التحقق من أن الإصدار منشور بالفعل
        $version = LegalDocumentVersion::where('id', $validated['version_id'])
            ->where('status', 'published')
            ->firstOrFail();

        // تجنب تكرار الموافقة على نفس الإصدار
        $exists = LegalDocumentAcceptance::where('user_id', $user->id)
            ->where('legal_document_version_id', $version->id)
            ->exists();

        if ($exists) {
            return api_success(null, 'لقد وافقت بالفعل على هذا الإصدار.');
        }

        $acceptance = LegalDocumentAcceptance::create([
            'user_id' => $user->id,
            'legal_document_version_id' => $version->id,
            'accepted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'company_id' => $version->company_id,
            'created_by' => $user->id,
        ]);

        return api_success(new LegalDocumentAcceptanceResource($acceptance), 'تم تسجيل موافقتك بنجاح.', 201);
    }

    /**
     * عرض سجل موافقات المستخدم الحالي التاريخي.
     */
    public function myHistory(): JsonResponse
    {
        $user = Auth::user();
        $acceptances = LegalDocumentAcceptance::with(['version.document'])
            ->where('user_id', $user->id)
            ->orderBy('accepted_at', 'desc')
            ->get();

        return api_success(LegalDocumentAcceptanceResource::collection($acceptances), 'تم جلب سجل الموافقات بنجاح.');
    }

    /**
     * جلب تقارير الإدارة التشغيلية بجميع الموافقات لإصدار مستند قانوني معين (للمسؤولين فقط).
     */
    public function report(Request $request, $versionId): JsonResponse
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo(perm_key('legal_documents.view_all')) && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك باستعراض هذه التقارير.');
        }

        $version = LegalDocumentVersion::findOrFail($versionId);

        // التحقق من صلاحية الوصول للشركة
        if ($version->company_id !== null && $version->company_id !== $user->active_company_id && !$user->hasPermissionTo(perm_key('admin.super'))) {
            abort(403, 'غير مصرح لك.');
        }

        $acceptances = LegalDocumentAcceptance::with(['user', 'version'])
            ->where('legal_document_version_id', $version->id)
            ->orderBy('accepted_at', 'desc')
            ->get();

        return api_success(LegalDocumentAcceptanceResource::collection($acceptances), 'تم جلب تقرير الموافقات بنجاح.');
    }
}
