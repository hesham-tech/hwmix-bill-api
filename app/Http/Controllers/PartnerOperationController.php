<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartnerOperation\StorePartnerOperationRequest;
use App\Http\Resources\PartnerOperation\PartnerOperationResource;
use App\Models\PartnerOperation;
use App\Models\User;
use App\Services\PartnerOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * المتحكم المسؤول عن إدارة واستقبال طلبات عمليات الشركاء وكشوفات الحساب
 */
class PartnerOperationController extends Controller
{
    protected PartnerOperationService $partnerOperationService;

    public function __construct(PartnerOperationService $partnerOperationService)
    {
        $this->partnerOperationService = $partnerOperationService;
    }

    /**
     * عرض قائمة عمليات الشركاء مع إمكانية الفلترة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PartnerOperation::with(['partner', 'cashBox', 'transaction']);

        if ($request->has('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('operation_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('operation_date', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $operations = $query->orderBy('operation_date', 'desc')->paginate($perPage);

        return PartnerOperationResource::collection($operations);
    }

    /**
     * إنشاء وتسجيل عملية جديدة للشريك
     */
    public function store(StorePartnerOperationRequest $request): JsonResponse
    {
        $operation = $this->partnerOperationService->executeOperation($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل عملية الشريك بنجاح وتحديث الأثر المالي.',
            'data' => new PartnerOperationResource($operation),
        ], 201);
    }

    /**
     * عرض تفاصيل عملية محددة
     */
    public function show(PartnerOperation $partnerOperation): JsonResponse
    {
        $partnerOperation->load(['partner', 'cashBox']);

        return response()->json([
            'success' => true,
            'data' => new PartnerOperationResource($partnerOperation),
        ]);
    }

    /**
     * عكس العملية المالية
     */
    public function destroy(PartnerOperation $partnerOperation): JsonResponse
    {
        try {
            $this->partnerOperationService->reverseOperation($partnerOperation, auth()->id());
            return response()->json([
                'success' => true,
                'message' => 'تم عكس العملية المالية للشريك بنجاح.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * استخراج كشف حساب الشريك
     */
    public function statement(Request $request, User $partner): JsonResponse
    {
        $statement = $this->partnerOperationService->getPartnerStatement(
            $partner->id,
            $request->query('date_from'),
            $request->query('date_to')
        );

        $statement['operations'] = PartnerOperationResource::collection($statement['operations']);

        return response()->json([
            'success' => true,
            'data' => $statement,
        ]);
    }

    /**
     * إرجاع قائمة أنواع العمليات المدعومة للشركاء
     */
    public function types(): JsonResponse
    {
        $types = [
            [
                'key' => 'capital_increase',
                'label' => 'زيادة رأس مال',
                'category' => 'deposit',
                'description' => 'إيداع أموال إضافية كزيادة في رأس مال الشركة',
            ],
            [
                'key' => 'capital_withdrawal',
                'label' => 'سحب من رأس المال',
                'category' => 'withdraw',
                'description' => 'سحب جزء من مساهمة رأس المال من الخزينة',
            ],
            [
                'key' => 'partner_loan_given',
                'label' => 'تقديم قرض للشركة',
                'category' => 'deposit',
                'description' => 'تقديم قروض مؤقتة من الشريك للشركة',
            ],
            [
                'key' => 'partner_loan_repaid',
                'label' => 'سداد قرض الشريك',
                'category' => 'withdraw',
                'description' => 'سداد القرض المستحق للشريك من خزينة الشركة',
            ],
            [
                'key' => 'profit_distribution',
                'label' => 'توزيع أرباح',
                'category' => 'withdraw',
                'description' => 'صرف أرباح نقدية مستحقة للشريك',
            ],
            [
                'key' => 'loss_coverage',
                'label' => 'تغطية خسائر',
                'category' => 'deposit',
                'description' => 'ضخ نقدية من الشريك لتغطية خسائر التشغيل',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}
