<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\Custody;
use Modules\Accounting\Services\CustodyService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\FinancialOperation;
use App\Contracts\FinancialEngineInterface;

class CustodyController extends Controller
{
    protected CustodyService $custodyService;

    public function __construct(CustodyService $custodyService)
    {
        $this->custodyService = $custodyService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return api_unauthorized('يتطلب المصادقة.');

        if (!$user->hasPermissionTo(perm_key('custodies.view_all')) && !$user->hasPermissionTo(perm_key('custodies.view_self'))) {
            return api_forbidden('ليس لديك إذن لعرض العهد.');
        }

        $query = Custody::with(['user', 'cashbox'])
            ->where('company_id', $user->active_company_id);

        if (!$user->hasPermissionTo(perm_key('custodies.view_all'))) {
            $query->where('user_id', $user->id);
        } else {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->input('cashbox_id'));
        }

        $perPage = max(1, (int)$request->get('per_page', 10));
        $custodies = $query->orderBy('issue_date', 'desc')->paginate($perPage);

        return api_success($custodies, 'تم استرداد العهد بنجاح.');
    }

    public function show(Request $request, Custody $custody): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return api_unauthorized('يتطلب المصادقة.');

        if ($custody->company_id !== $user->active_company_id) {
            return api_forbidden('العهدة المحددة لا تنتمي لشركتك.');
        }

        if (!$user->hasPermissionTo(perm_key('custodies.view_all'))) {
            if (!$user->hasPermissionTo(perm_key('custodies.view_self')) || $custody->user_id !== $user->id) {
                return api_forbidden('ليس لديك إذن لعرض هذه العهدة.');
            }
        }

        $custody->load(['user', 'cashbox', 'expenses.category']);
        
        $operation = FinancialOperation::where('source_type', Custody::class)
            ->where('source_id', $custody->id)
            ->where('type', 'custody_issue')
            ->first();

        if ($operation) {
            $custody->setAttribute('financial_operation', $operation);
        }

        return api_success($custody, 'تم استرداد بيانات العهدة.');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) return api_unauthorized('يتطلب المصادقة.');

        if (!$user->hasPermissionTo(perm_key('custodies.create'))) {
            return api_forbidden('ليس لديك إذن لإصدار عهدة.');
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cashbox_id' => 'required|exists:cash_boxes,id',
            'amount' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return api_error('بيانات غير صالحة', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $data['company_id'] = $user->active_company_id;
        $data['created_by'] = $user->id;

        $custody = $this->custodyService->issueCustody($data);

        return api_success($custody, 'تم إصدار العهدة بنجاح.', 201);
    }

    public function refund(Request $request, Custody $custody)
    {
        $user = Auth::user();
        if (!$user) return api_unauthorized('يتطلب المصادقة.');

        if ($custody->company_id !== $user->active_company_id) {
            return api_forbidden('العهدة المحددة لا تنتمي لشركتك.');
        }

        if (!$user->hasPermissionTo(perm_key('custodies.refund'))) {
            return api_forbidden('ليس لديك إذن لإجراء استرداد نقدي من العهدة.');
        }

        $validator = Validator::make($request->all(), [
            'cashbox_id' => 'required|exists:cash_boxes,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return api_error('بيانات غير صالحة', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $refund = $this->custodyService->refund($custody, $data);
        return api_success($refund, 'تم تسجيل الاسترداد بنجاح.');
    }

    public function reverse(Request $request, Custody $custody): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return api_unauthorized('يتطلب المصادقة.');

        if ($custody->company_id !== $user->active_company_id) {
            return api_forbidden('العهدة المحددة لا تنتمي لشركتك.');
        }

        if (!$user->hasPermissionTo(perm_key('custodies.reverse'))) {
            return api_forbidden('ليس لديك إذن لعكس العهدة.');
        }

        $operation = FinancialOperation::where('source_type', Custody::class)
            ->where('source_id', $custody->id)
            ->where('type', 'custody_issue')
            ->first();

        if (!$operation) {
            return api_error('العملية المالية المرتبطة غير موجودة.', [], 404);
        }

        if ($operation->status === 'canceled' || $operation->status === 'reversed') {
            return api_error('تم عكس هذه العهدة مسبقاً.', [], 422);
        }

        $engine = app(FinancialEngineInterface::class);
        $engine->reverseOperation($operation->id, 'تم الإلغاء عبر الـ API');
        
        return api_success(null, 'تم عكس العهدة بنجاح.');
    }
}


