<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CashBox;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Support\Facades\Auth;
use Throwable;

// تأكد من أن هذه الدالة متاحة عالميًا أو داخل هذا النطاق
if (!function_exists('perm_key')) {
    function perm_key(string $permission): string
    {
        return $permission;
    }
}

class TransactionController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'customer',
            'targetCustomer',
            'cashbox',
            'targetCashbox',
            'company',
            'creator',
        ];
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تحويل الرصيد (Transfer)
     * 
     * إجراء عملية تحويل رصيد من صندوق مستخدم إلى صندوق مستخدم آخر أو لنفس المستخدم في صندوق آخر.
     * 
     * @bodyParam target_user_id integer required معرف المستخدم المستهدف. Example: 2
     * @bodyParam amount number required المبلغ. Example: 100
     * @bodyParam from_cash_box_id integer required معرف صندوق الصادر. Example: 1
     * @bodyParam to_cash_box_id integer required معرف صندوق الوارد. Example: 2
     * @bodyParam description string وصف المعاملة. Example: تحويل داخلي
     */
    public function transfer(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية خاصة لتحويل الأموال (موازية لـ balance.transfer)
            if (
                !$authUser->hasPermissionTo(perm_key('admin.super')) &&
                !$authUser->hasPermissionTo(perm_key('balance.transfer')) &&
                !$authUser->hasPermissionTo(perm_key('admin.company'))
            ) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            $validated = $request->validate([
                'from_user_id' => 'nullable|exists:users,id',
                'target_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'from_cash_box_id' => [
                    'nullable',
                    'exists:cash_boxes,id',
                    function ($attribute, $value, $fail) use ($authUser, $companyId) {
                        if (!$value)
                            return;
                        $cashBox = CashBox::with(['company'])->find($value);
                        if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                            $fail('صندوق النقد المصدر المحدد غير صالح أو غير متاح.');
                        }
                    }
                ],
                'to_cash_box_id' => [
                    'nullable',
                    'exists:cash_boxes,id',
                    'different:from_cash_box_id',
                    function ($attribute, $value, $fail) use ($authUser, $companyId) {
                        if (!$value)
                            return;
                        $toCashBox = CashBox::with(['company'])->find($value);
                        if (!$toCashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $toCashBox->company_id !== $companyId)) {
                            $fail('صندوق النقد المستهدف المحدد غير صالح أو غير متاح.');
                        }
                    }
                ],
                'description' => 'nullable|string',
            ]);

            $sourceUserId = $validated['from_user_id'] ?? $authUser->id;
            $sourceUser = User::findOrFail($sourceUserId);
            $targetUser = User::findOrFail($validated['target_user_id']);

            // [جديد]: إذا كان المستخدم يحاول التحويل من حساب شخص آخر، يتطلب صلاحية balance.transfer_any
            if ($sourceUserId !== $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.transfer_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للتحويل من حساب مستخدم آخر.');
            }

            // [تحسين]: استخدام الصناديق الافتراضية إذا لم يتم تمرير معرفات محددة
            $fromCashBoxId = $validated['from_cash_box_id'] ?? $sourceUser->getDefaultCashBoxForCompany($companyId)?->id;
            $toCashBoxId = $validated['to_cash_box_id'] ?? $targetUser->getDefaultCashBoxForCompany($companyId)?->id;

            if (!$fromCashBoxId || !$toCashBoxId) {
                return api_error('لا يمكن إتمام التحويل لعدم وجود صناديق نقدية افتراضية للمستخدمين.', [], 422);
            }

            $fromCashBox = CashBox::findOrFail($fromCashBoxId);
            $toCashBox = CashBox::findOrFail($toCashBoxId);

            // التحقق من أن الصناديق تابعة لشركة المستخدم (أو أن المستخدم super_admin)
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if ($fromCashBox->company_id !== $companyId || $toCashBox->company_id !== $companyId) {
                    return api_forbidden('يمكنك فقط تحويل الأموال بين الصناديق النقدية داخل شركتك.');
                }

                // [جديد]: إذا كان المستخدم يملك فقط صلاحية التحويل الشخصي، يجب أن يكون الصندوق المصدر ملكه (أو ملك العميل المختار إذا كان لديه صلاحية any)
                if (
                    !$authUser->hasPermissionTo(perm_key('balance.transfer_any')) &&
                    !$authUser->hasPermissionTo(perm_key('admin.company')) &&
                    $fromCashBox->user_id !== $authUser->id
                ) {
                    return api_forbidden('لا يمكنك التحويل إلا من خزنتك الشخصية.');
                }
            }

            DB::beginTransaction();
            try {
                // تنفيذ التحويل باسم المستخدم المصدر وليس بالضرورة المستخدم الموثق
                $sourceUser->transferTo(
                    $targetUser,
                    $validated['amount'],
                    $fromCashBoxId,
                    $toCashBoxId,
                    $validated['description'] ?? "تحويل من {$sourceUser->name} إلى {$targetUser->name}"
                );

                DB::commit();
                return api_success([], 'تم التحويل بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل التحويل. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء تحويل الأموال.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * سجل عمليات المستخدم في خزنة
     * 
     * @urlParam cashBoxId required معرف الخزنة. Example: 1
     * @queryParam type string نوع العملية (إيداع، سحب، تحويل). Example: إيداع
     * @queryParam created_at_from date تاريخ من. Example: 2023-01-01
     * @queryParam per_page integer عدد النتائج. Default: 20
     */
    public function userTransactions(Request $request, $cashBoxId)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser || !$authUser->company_id) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $query = Transaction::with($this->relations);

            $query->where('user_id', $authUser->id)
                ->where('company_id', $authUser->company_id)
                ->where('cashbox_id', $cashBoxId);

            // فلاتر البحث
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }
            if ($request->filled('target_user_id')) {
                $query->where('target_user_id', $request->input('target_user_id'));
            }
            $createdAtFrom = $request->input('created_at_from');
            if (!empty($createdAtFrom)) {
                $query->where('created_at', '>=', $createdAtFrom . ' 00:00:00');
            }
            $createdAtTo = $request->input('created_at_to');
            if (!empty($createdAtTo)) {
                $query->where('created_at', '<=', $createdAtTo . ' 23:59:59');
            }

            $perPage = max(1, $request->get('per_page', 20));
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $transactions = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            // استخدام api_success مع Pagination
            if ($transactions->isEmpty()) {
                return api_success([], 'لم يتم العثور على معاملات مستخدم.');
            } else {
                return api_success(TransactionResource::collection($transactions), 'تم جلب معاملات المستخدم بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * إيداع نقدي (Deposit)
     * 
     * @bodyParam amount number required المبلغ المودع. Example: 1000
     * @bodyParam cash_box_id integer required معرف الخزنة. Example: 1
     * @bodyParam description string وصف الإيداع. Example: إيداع رأس مال
     */
    public function deposit(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية الإيداع (تندرج تحت إنشاء معاملة)
            if (
                !$authUser->hasPermissionTo(perm_key('admin.super')) &&
                !$authUser->hasPermissionTo(perm_key('balance.deposit')) &&
                !$authUser->hasPermissionTo(perm_key('admin.company'))
            ) {
                return api_forbidden('ليس لديك إذن لإجراء إيداع.');
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => [
                    'nullable',
                    'exists:cash_boxes,id',
                ],
                'description' => 'nullable|string',
            ]);

            $targetUserId = $validated['user_id'] ?? $authUser->id;
            $targetUser = User::findOrFail($targetUserId);

            // التحقق من صلاحية الإيداع لمستخدم آخر
            if ($targetUserId != $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.deposit_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للإيداع في حساب مستخدم آخر.');
            }

            // إذا لم يتم تحديد خزنة، نستخدم الخزنة الافتراضية للمستخدم المستهدف في الشركة الحالية
            $cashBoxId = $validated['cash_box_id'] ?? null;
            if (!$cashBoxId) {
                $cashBox = $targetUser->getDefaultCashBoxForCompany($companyId);
                if (!$cashBox) {
                    return api_error('المستخدم المستهدف ليس له خزنة في هذه الشركة.', [], 422);
                }
                $cashBoxId = $cashBox->id;
            } else {
                // التأكد من أن الخزنة تخص المستخدم المستهدف وأنها تابعة للشركة
                $cashBox = CashBox::where('id', $cashBoxId)->where('user_id', $targetUserId)->where('company_id', $companyId)->first();
                if (!$cashBox) {
                    return api_error('صندوق النقد المحدد غير صالح أو لا يتبع للمستخدم المستهدف في هذه الشركة.', [], 422);
                }
            }

            DB::beginTransaction();
            try {
                $targetUser->deposit(
                    $validated['amount'],
                    $cashBoxId,
                    $validated['description'] ?? 'إيداع نقدي خارجي'
                );

                DB::commit();
                return api_success([], 'تم الإيداع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل الإيداع. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء الإيداع.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * سحب نقدي (Withdraw)
     * 
     * @bodyParam amount number required المبلغ المسحوب. Example: 200
     * @bodyParam cash_box_id integer required معرف الخزنة. Example: 1
     * @bodyParam description string وصف السحب. Example: مصاريف نثرية
     */
    public function withdraw(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية السحب (تندرج تحت إنشاء معاملة)
            if (
                !$authUser->hasPermissionTo(perm_key('admin.super')) &&
                !$authUser->hasPermissionTo(perm_key('balance.withdraw')) &&
                !$authUser->hasPermissionTo(perm_key('admin.company'))
            ) {
                return api_forbidden('ليس لديك إذن لإجراء سحب.');
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => [
                    'nullable',
                    'exists:cash_boxes,id',
                ],
                'description' => 'nullable|string',
            ]);

            $targetUserId = $validated['user_id'] ?? $authUser->id;
            $targetUser = User::findOrFail($targetUserId);

            // التحقق من صلاحية السحب من مستخدم آخر
            if ($targetUserId != $authUser->id && !$authUser->hasPermissionTo(perm_key('balance.withdraw_any')) && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('ليس لديك إذن للسحب من حساب مستخدم آخر.');
            }

            // إذا لم يتم تحديد خزنة، نستخدم الخزنة الافتراضية
            $cashBoxId = $validated['cash_box_id'] ?? null;
            if (!$cashBoxId) {
                $cashBox = $targetUser->getDefaultCashBoxForCompany($companyId);
                if (!$cashBox) {
                    return api_error('المستخدم المستهدف ليس له خزنة في هذه الشركة.', [], 422);
                }
                $cashBoxId = $cashBox->id;
            } else {
                $cashBox = CashBox::where('id', $cashBoxId)->where('user_id', $targetUserId)->where('company_id', $companyId)->first();
                if (!$cashBox) {
                    return api_error('صندوق النقد المحدد غير صالح أو لا يتبع للمستخدم المستهدف في هذه الشركة.', [], 422);
                }
            }

            DB::beginTransaction();
            try {
                // تحقق من الرصيد في حالة الصناديق النقدية الحقيقية فقط (عهدة الموظف)
                // أما أرصدة العملاء والموردين فيمكن أن تكون سالبة (ديون) أو موجبة (أمانات)
                if ($cashBox->user_id === $targetUserId && $targetUser->isStaffOrAdmin()) {
                    $currentBalance = $targetUser->balanceBox($cashBoxId);
                    if ($currentBalance < $validated['amount']) {
                        DB::rollBack();
                        return api_error('الرصيد غير كافٍ في عهدة الموظف.', [], 422);
                    }
                }

                $targetUser->withdraw(
                    $validated['amount'],
                    $cashBoxId,
                    $validated['description'] ?? 'سحب نقدي خارجي'
                );

                DB::commit();
                return api_success([], 'تم السحب بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل السحب. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء السحب.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض جميع المعاملات (للمدراء)
     * 
     * @queryParam type string نوع المعاملة. Example: سحب
     * @queryParam user_id integer معرف المستخدم. Example: 1
     * @queryParam cashbox_id integer معرف الخزنة. Example: 1
     * @queryParam created_at_from date تاريخ من. Example: 2023-01-01
     */
    public function transactions(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $query = Transaction::with($this->relations);

            // تطبيق شروط الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // استرجاع جميع المعاملات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('transactions.view_all'), perm_key('admin.company')])) {
                // يرى جميع المعاملات ضمن الشركة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('transactions.view_children'))) {
                // يرى المعاملات التي أنشأها هو أو المستخدمون التابعون له، ضمن الشركة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('transactions.view_self'))) {
                // يرى المعاملات التي أنشأها المستخدم فقط، ومرتبطة بالشركة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // العميل: يرى معاملاته الشخصية
                $query->where('user_id', $authUser->id);
            }

            // فلاتر البحث
            if ($request->has('created_at_from')) {
                $createdAtFrom = $request->get('created_at_from');
                if ($createdAtFrom) {
                    $query->where('created_at', '>=', $createdAtFrom . ' 00:00:00');
                }
            }
            if ($request->has('created_at_to')) {
                $createdAtTo = $request->get('created_at_to');
                if ($createdAtTo) {
                    $query->where('created_at', '<=', $createdAtTo . ' 23:59:59');
                }
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }

            // تحديد الترتيب
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // الخريطة للأعمدة غير المتطابقة
            $allowedSortFields = [
                'id' => 'id',
                'type' => 'type',
                'amount' => 'amount',
                'created_at' => 'created_at',
                'transaction_date' => 'created_at', // خريطة لـ created_at
            ];

            $finalSortField = $allowedSortFields[$sortField] ?? 'created_at';
            $query->orderBy($finalSortField, $sortOrder);

            // تقسيم النتائج إلى صفحات
            $perPage = max(1, $request->get('per_page', 10));
            $transactions = $query->paginate($perPage);

            // استخدام api_success مع Pagination
            return api_success($transactions, 'تم استرداد المعاملات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عكس معاملة (Reverse)
     * 
     * إلغاء معاملة سابقة وإرجاع الحالة كما كانت.
     * 
     * @urlParam transactionId required المعرف الفريد للمعاملة (UUID). Example: 9abc-123...
     */
    public function reverseTransaction(string $transactionId)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            DB::beginTransaction();

            try {
                // استرجاع المعاملة المطلوبة وتحميل العلاقات للتحقق من الصلاحيات
                $transaction = Transaction::with(['customer', 'targetCustomer', 'cashbox', 'targetCashbox', 'company', 'creator'])->findOrFail($transactionId);

                // التحقق من الصلاحيات بناءً على الأذونات
                $canReverse = false;
                if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                    $canReverse = true;
                } elseif ($authUser->hasAnyPermission([perm_key('transactions.update_all'), perm_key('admin.company')])) {
                    $canReverse = $transaction->belongsToCurrentCompany();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_children'))) {
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByUserOrChildren();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_self'))) {
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByCurrentUser();
                } else {
                    return api_forbidden('ليس لديك إذن لعكس هذه المعاملة.');
                }

                if (!$canReverse) {
                    return api_forbidden('ليس لديك إذن لعكس هذه المعاملة.');
                }

                // التحقق من نوع المعاملة
                switch ($transaction->type) {
                    case 'تحويل_صادر':
                        $transaction->reverseTransfer();
                        break;
                    case 'سحب':
                        $transaction->reverseWithdraw();
                        break;
                    case 'إيداع':
                        $transaction->reverseDeposit();
                        break;
                    default:
                        throw new \Exception('نوع المعاملة غير مدعوم للعكس: ' . $transaction->type);
                }

                // تحديد القيم للمعاملة العكسية بناءً على النوع
                $reverseData = [
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->cashbox?->balance + ($transaction->type === 'إيداع' ? $transaction->amount : -$transaction->amount),
                    'balance_after' => $transaction->cashbox?->balance,
                    'description' => 'عكس المعاملة الأصلية رقم: ' . $transaction->id,
                    'original_transaction_id' => $transaction->id,
                ];

                if ($transaction->type === 'تحويل_صادر') {
                    $reverseData['user_id'] = $transaction->target_user_id;
                    $reverseData['cashbox_id'] = $transaction->target_cashbox_id;
                    $reverseData['target_user_id'] = $transaction->user_id;
                    $reverseData['target_cashbox_id'] = $transaction->cashbox_id;
                    $reverseData['type'] = 'عكس_تحويل';
                } elseif ($transaction->type === 'إيداع') {
                    $reverseData['user_id'] = $transaction->user_id;
                    $reverseData['cashbox_id'] = $transaction->cashbox_id;
                    $reverseData['type'] = 'عكس_إيداع';
                    $reverseData['amount'] = -$transaction->amount;
                } elseif ($transaction->type === 'سحب') {
                    $reverseData['user_id'] = $transaction->user_id;
                    $reverseData['cashbox_id'] = $transaction->cashbox_id;
                    $reverseData['type'] = 'عكس_سحب';
                    $reverseData['amount'] = $transaction->amount;
                }

                $reversedTransaction = Transaction::create($reverseData);

                DB::commit();
                return api_success(new TransactionResource($reversedTransaction), 'تم عكس المعاملة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل عكس المعاملة. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
