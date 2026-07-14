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
            
            // تطبيق منطق الصلاحيات: 
            // 1. افتراضياً: يرى كل مستخدم (حتى السوبر أدمن) صناديقه الخاصة فقط لتفادي الأخطاء.
            // 2. اختيارياً: إذا تم إرسال المعامل all_company_boxes وكان المستخدم يملك صلاحيات إدارية؛ يتم جلب كل صناديق الشركة.
            $showAllBoxes = $request->boolean('all_company_boxes') && 
                ($authUser->hasPermissionTo(perm_key('admin.super')) || 
                 $authUser->hasPermissionTo(perm_key('admin.company')) || 
                 $authUser->hasPermissionTo(perm_key('cash_boxes.view_all')));

            if (!$showAllBoxes) {
                $branchId = config('app.active_branch_id') ?? $authUser->branch_id;
                $cashBoxQuery->where(function($q) use ($authUser, $branchId) {
                    $q->where('user_id', $authUser->id)
                      ->orWhere(function($subQ) use ($authUser, $branchId) {
                          $subQ->whereNull('user_id')
                               ->whereHas('users', function($userQ) use ($authUser) {
                                   $userQ->where('users.id', $authUser->id);
                               });
                          if ($branchId) {
                              $subQ->where('branch_id', $branchId);
                          }
                      });
                });
            }

            // تطبيق فلاتر الحالة والـ scopes
            if ($request->has('status')) {
                $cashBoxQuery->where('status', $request->get('status'));
            } elseif ($request->has('is_active')) {
                $cashBoxQuery->where('status', $request->boolean('is_active') ? \App\Enums\CashBoxStatus::ACTIVE->value : \App\Enums\CashBoxStatus::INACTIVE->value);
            } elseif ($request->boolean('include_legacy')) {
                // جلب كل شيء بما فيها مؤرشفة
            } elseif ($request->boolean('include_inactive')) {
                $cashBoxQuery->whereIn('status', [
                    \App\Enums\CashBoxStatus::ACTIVE->value,
                    \App\Enums\CashBoxStatus::INACTIVE->value,
                    \App\Enums\CashBoxStatus::LOCKED->value,
                    \App\Enums\CashBoxStatus::DRAFT->value
                ]);
            } else {
                // الافتراضي: usable
                $cashBoxQuery->usable();
            }

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
                if (!array_key_exists('user_id', $validatedData)) {
                    $validatedData['user_id'] = $authUser->id;
                }

                if (!array_key_exists('branch_id', $validatedData)) {
                    $validatedData['branch_id'] = config('app.active_branch_id') ?? $authUser->branch_id;
                }

                if ($validatedData['user_id'] !== null) {
                    $targetUser = \App\Models\User::withoutGlobalScopes()->find($validatedData['user_id']);
                    if (!$targetUser || !$targetUser->hasCapability('has_cash_custody', $validatedData['company_id'])) {
                        DB::rollBack();
                        return api_error('المستخدم المستهدف لا يملك صلاحية/قدرة عهدة نقدية.', [], 422);
                    }
                }

                if ($validatedData['company_id'] != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خزن لشركتك الحالية.');
                }

                $validatedData['created_by'] = $authUser->id;

                $cashBox = app(\App\Services\CashBoxLifecycleService::class)->create($validatedData, $authUser);

                // حفظ إعداد الخزينة الافتراضية للمستخدم في جدول المستخدمين
                if (isset($request->is_default) && $request->is_default) {
                    $userId = $validatedData['user_id'] ?? $authUser->id;
                    $targetUser = \App\Models\User::withoutGlobalScopes()->find($userId);
                    if ($targetUser) {
                        app(\App\Services\CashBoxLifecycleService::class)->changeDefault($targetUser, $cashBox->id, $authUser);
                    }
                }

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
                $lifecycle = app(\App\Services\CashBoxLifecycleService::class);

                // 1. التعامل مع تغيير المالك المسؤول عن العهدة
                if (array_key_exists('user_id', $validatedData)) {
                    $newUserId = $validatedData['user_id'];
                    if ($newUserId !== $cashBox->user_id) {
                        $lifecycle->assignOwner($cashBox, $newUserId, $authUser);
                    }
                }

                // 2. التعامل مع الحالة وآلة الحالة
                if (array_key_exists('status', $validatedData)) {
                    $newStatus = \App\Enums\CashBoxStatus::from($validatedData['status']);
                    if ($newStatus === \App\Enums\CashBoxStatus::ACTIVE) {
                        $lifecycle->activate($cashBox, $authUser);
                    } elseif ($newStatus === \App\Enums\CashBoxStatus::INACTIVE) {
                        $lifecycle->deactivate($cashBox, $authUser);
                    } elseif ($newStatus === \App\Enums\CashBoxStatus::ARCHIVED) {
                        $lifecycle->archive($cashBox, $authUser);
                    }
                } elseif (array_key_exists('is_active', $validatedData)) {
                    if ($request->boolean('is_active')) {
                        $lifecycle->activate($cashBox, $authUser);
                    } else {
                        $lifecycle->deactivate($cashBox, $authUser);
                    }
                }

                // 3. تحديث باقي البيانات الوصفية (الاسم، الوصف، رقم الحساب...)
                // نستبعد الحقول الأساسية التي تم علاجها عبر Lifecycle لضمان عدم تخطي القواعد
                $otherData = array_diff_key($validatedData, array_flip(['user_id', 'status', 'is_active', 'balance', 'code', 'company_id', 'branch_id']));
                if (!empty($otherData)) {
                    $cashBox->update($otherData);
                }

                // 4. حفظ إعداد الخزينة الافتراضية للمستخدم
                if (isset($request->is_default)) {
                    $userId = $cashBox->user_id ?? $authUser->id;
                    $targetUser = \App\Models\User::withoutGlobalScopes()->find($userId);
                    if ($targetUser) {
                        if ($request->is_default) {
                            $lifecycle->changeDefault($targetUser, $cashBox->id, $authUser);
                        } else {
                            if ($targetUser->default_cash_box_id === $cashBox->id) {
                                $lifecycle->changeDefault($targetUser, null, $authUser);
                            }
                        }
                    }
                }

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
                // منع الحذف المادي نهائياً وإرجاع رسالة خطأ واضحة
                DB::rollback();
                return api_error('لا يمكن حذف الخزائن نهائياً من النظام لضمان النزاهة التاريخية للعمليات المالية. يرجى تعطيل أو أرشفة الخزنة بدلاً من ذلك.', [], 400);
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

            if (!$authUser->canAccessCashBox($fromCashBox)) {
                return api_forbidden('ليس لديك صلاحية الوصول إلى الخزينة المصدر.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if ($fromCashBox->company_id !== $companyId || $toCashBox->company_id !== $companyId) {
                    return api_forbidden('يمكنك فقط تحويل الأموال بين الخزن داخل شركتك.');
                }
            }

            $engine = app(\App\Contracts\FinancialEngineInterface::class);
            $operationId = (string) \Illuminate\Support\Str::uuid();
            $description = $validated['description'] ?? ($authUser->id == $toUser->id ? "تحويل داخلي بين {$fromCashBox->name} إلى {$toCashBox->name}" : "تحويل من {$authUser->nickname} إلى {$toUser->nickname}");

            DB::beginTransaction();
            try {
                $engine->transferCash(
                    $fromCashBoxId,
                    $toCashBoxId,
                    (float)$amount,
                    $operationId,
                    $description
                );

                DB::commit();
                return api_success([], 'تم تحويل الأموال بنجاح!');
            } catch (Throwable $e) {
                DB::rollback();
                $code = (str_contains($e->getMessage(), 'الرصيد غير كاف') || str_contains($e->getMessage(), 'insufficient')) ? 422 : 500;
                return api_error($e->getMessage() ?: 'فشل تحويل الأموال.', [], $code);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id ?? null;
            if (!$authUser || !$companyId) return api_unauthorized('يتطلب المصادقة.');

            $summaryData = DB::table('cash_boxes')
                ->join('cash_box_types', 'cash_boxes.cash_box_type_id', '=', 'cash_box_types.id')
                ->where('cash_boxes.company_id', $companyId)
                ->where('cash_boxes.status', 'active')
                ->select(
                    DB::raw("SUM(CASE WHEN cash_box_types.name = 'نقدي' THEN cash_boxes.balance ELSE 0 END) as total_cash"),
                    DB::raw("SUM(CASE WHEN cash_box_types.name = 'حساب بنكي' THEN cash_boxes.balance ELSE 0 END) as total_bank"),
                    DB::raw("SUM(CASE WHEN cash_box_types.name NOT IN ('نقدي', 'حساب بنكي') THEN cash_boxes.balance ELSE 0 END) as total_wallets"),
                    DB::raw("SUM(cash_boxes.balance) as total_all")
                )
                ->first();

            return api_success([
                'total_cash' => (float)($summaryData->total_cash ?? 0),
                'total_bank' => (float)($summaryData->total_bank ?? 0),
                'total_wallets' => (float)($summaryData->total_wallets ?? 0),
                'total_all' => (float)($summaryData->total_all ?? 0),
            ], 'تم جلب ملخص الخزن بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function getUsers(CashBox $cashBox): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            $companyId = $authUser->active_company_id;

            $assignedUserIds = $cashBox->users()->pluck('users.id')->toArray();

            $companyUsers = User::whereHas('companies', function($q) use ($companyId) {
                $q->where('companies.id', $companyId);
            })->get(['id', 'nickname', 'full_name', 'email']);

            return api_success([
                'assigned_user_ids' => $assignedUserIds,
                'company_users' => $companyUsers
            ], 'تم استرداد مستخدمي الخزينة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    public function syncUsers(Request $request, CashBox $cashBox): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) return api_unauthorized('يتطلب المصادقة.');

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('admin.company')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.update_all'))) {
                return api_forbidden('ليس لديك إذن لإجراء هذه العملية.');
            }

            $validated = $request->validate([
                'user_ids' => 'present|array',
                'user_ids.*' => 'exists:users,id',
            ]);

            $syncData = [];
            foreach ($validated['user_ids'] as $userId) {
                $syncData[$userId] = ['created_by' => $authUser->id];
            }

            $cashBox->users()->sync($syncData);

            return api_success([], 'تم تحديث مستخدمي الخزينة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
