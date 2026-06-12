<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreCashBoxRequest;
use Modules\Accounting\Http\Requests\UpdateCashBoxRequest;
use Modules\Accounting\Http\Resources\CashBoxResource;
use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * متحكم الصناديق (CashBoxController) - موديول المحاسبة
 */
class CashBoxController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'typeBox',
            'company',
            'creator',
            'user',
            'branch',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $cashBoxQuery = CashBox::query()->with($this->relations);
            
            // تطبيق منطق الصلاحيات: كل مستخدم يرى صناديقه فقط بناءً على طلب العميل
            $cashBoxQuery->where('user_id', $authUser->id);

            if (!empty($request->get('name'))) {
                $cashBoxQuery->where('name', 'like', '%' . $request->get('name') . '%');
            }
            if (!empty($request->get('account_number'))) {
                $cashBoxQuery->where('account_number', 'like', '%' . $request->get('account_number') . '%');
            }
            if ($request->boolean('current_user')) {
                $cashBoxQuery->where('user_id', $authUser->id);
            }
            if (!empty($request->get('user_id'))) {
                $cashBoxQuery->where('user_id', $request->get('user_id'));
            }

            $perPageParam = $request->get('per_page', 10);
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');

            $cashBoxQuery->orderBy($sortField, $sortOrder);

            if ($perPageParam == -1) {
                $cashBoxes = $cashBoxQuery->get();
                $data = CashBoxResource::collection($cashBoxes);
            } else {
                $perPage = max(1, (int) $perPageParam);
                $paginated = $cashBoxQuery->paginate($perPage);
                $data = CashBoxResource::collection($paginated);
            }

            return api_success($data, $data->isEmpty() ? 'لم يتم العثور على خزن.' : 'تم استرداد الخزن بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function store(StoreCashBoxRequest $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;

            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خزن.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['company_id'] = $validatedData['company_id'] ?? $companyId;
                $validatedData['user_id'] = $validatedData['user_id'] ?? $authUser->id;

                if (!array_key_exists('branch_id', $validatedData)) {
                    $validatedData['branch_id'] = config('app.active_branch_id') ?? $authUser->branch_id;
                }

                if ($validatedData['company_id'] != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خزن لشركتك الحالية.');
                }

                $validatedData['created_by'] = $authUser->id;
                $cashBox = CashBox::create($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                return api_success(new CashBoxResource($cashBox), 'تم إنشاء الخزنة بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function show(CashBox $cashBox): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.view_all'), perm_key('admin.company')])) {
                $canView = true;
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_children'))) {
                $canView = $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_self'))) {
                $canView = $cashBox->createdByCurrentUser();
            }

            if ($canView) {
                $cashBox->load($this->relations);
                return api_success(new CashBoxResource($cashBox), 'تم استرداد تفاصيل الخزنة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الخزنة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function update(UpdateCashBoxRequest $request, CashBox $cashBox): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.update_all'), perm_key('admin.company')])) {
                $canUpdate = true;
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_children'))) {
                $canUpdate = $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_self'))) {
                $canUpdate = $cashBox->createdByCurrentUser();
            }

            if (!$canUpdate) return api_forbidden('ليس لديك إذن لتحديث هذه الخزنة.');

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $cashBox->update($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                return api_success(new CashBoxResource($cashBox), 'تم تحديث الخزنة بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function destroy(CashBox $cashBox): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.delete_all'), perm_key('admin.company')])) {
                $canDelete = true;
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_children'))) {
                $canDelete = $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_self'))) {
                $canDelete = $cashBox->createdByCurrentUser();
            }

            if (!$canDelete) return api_forbidden('ليس لديك إذن لحذف هذه الخزنة.');

            DB::beginTransaction();
            try {
                if (Transaction::where('cashbox_id', $cashBox->id)->exists() || Transaction::where('target_cashbox_id', $cashBox->id)->exists()) {
                    DB::rollback();
                    return api_error('لا يمكن حذف الخزنة لوجود معاملات مرتبطة.', [], 409);
                }

                $deletedCashBox = $cashBox->replicate();
                $deletedCashBox->setRelations($cashBox->getRelations());
                $cashBox->delete();
                DB::commit();
                return api_success(new CashBoxResource($deletedCashBox), 'تم حذف الخزنة بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function transferFunds(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.transfer_funds')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            $validated = $request->validate([
                'to_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => 'required|exists:cash_boxes,id',
                'to_cash_box_id' => 'required|exists:cash_boxes,id|different:cash_box_id',
                'description' => 'nullable|string',
            ]);

            $toUser = User::findOrFail($validated['to_user_id']);
            $amount = $validated['amount'];
            $fromCashBoxId = $validated['cash_box_id'];
            $toCashBoxId = $validated['to_cash_box_id'];

            $fromCashBox = CashBox::findOrFail($fromCashBoxId);
            $toCashBox = CashBox::findOrFail($toCashBoxId);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if ($fromCashBox->company_id !== $companyId || $toCashBox->company_id !== $companyId) {
                    return api_forbidden('يمكنك فقط تحويل الأموال بين الخزن داخل شركتك.');
                }
            }

            $authUserBalance = $authUser->balanceBox($fromCashBoxId);
            if ($authUserBalance < $amount) {
                return api_error('الرصيد غير كافٍ في صندوق النقد المصدر.', [], 422);
            }

            $description = $validated['description'] ?? ($authUser->id == $toUser->id ? "تحويل داخلي بين {$fromCashBox->name} إلى {$toCashBox->name}" : "تحويل من {$authUser->nickname} إلى {$toUser->nickname}");

            \App\Models\Transaction::$preventObserverLog = true;
            DB::beginTransaction();
            try {
                Transaction::create([
                    'user_id' => $authUser->id,
                    'cashbox_id' => $fromCashBoxId,
                    'target_user_id' => $toUser->id,
                    'target_cashbox_id' => $toCashBoxId,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'type' => 'transfer_out',
                    'amount' => $amount,
                    'balance_before' => $authUserBalance,
                    'balance_after' => $authUserBalance - $amount,
                    'description' => $description,
                ]);

                $toUserBalance = $toUser->balanceBox($toCashBoxId);
                Transaction::create([
                    'user_id' => $toUser->id,
                    'cashbox_id' => $toCashBoxId,
                    'target_user_id' => $authUser->id,
                    'target_cashbox_id' => $fromCashBoxId,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'type' => 'transfer_in',
                    'amount' => $amount,
                    'balance_before' => $toUserBalance,
                    'balance_after' => $toUserBalance + $amount,
                    'description' => "استلام من {$authUser->nickname}",
                ]);

                $authUser->withdraw($amount, $fromCashBoxId, null, false);
                $toUser->deposit($amount, $toCashBoxId, null, false);

                DB::commit();
                return api_success([], 'تم تحويل الأموال بنجاح!');
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            } finally {
                \App\Models\Transaction::$preventObserverLog = false;
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
