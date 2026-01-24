<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\CompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\User\UserResource;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\CompanyUser\CompanyUserResource;
use App\Http\Resources\CompanyUser\CompanyUserBasicResource;
use App\Models\CashBoxType;


class UserController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'companies.logo',
            'cashBoxes',
            'creator',
            'companies',
            'activeCompanyUser.company',
        ];
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * عرض قائمة المستخدمين
     * 
     * @queryParam nickname string فلترة حسب اللقب.
     * @queryParam phone string فلترة حسب الهاتف.
     * @queryParam per_page integer عدد النتائج.
     * 
     * @apiResourceCollection App\Http\Resources\CompanyUser\CompanyUserResource
     * @apiResourceModel App\Models\CompanyUser
     */
    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * عرض قائمة المستخدمين (عالمياً للسوبر أدمن، أو حسب الشركة للمديرين)
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized();
            }

            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->can(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));

            // تحديد إذا كان المطلوب هو العرض العالمي (للسوبر أدمن فقط)
            $isGlobalView = filter_var($request->input('global', false), FILTER_VALIDATE_BOOLEAN) && $isSuperAdmin;

            if ($isGlobalView) {
                // العرض العالمي: جلب سجلات فريدة من جدول users
                $query = User::query()->with(['companies', 'creator']);
            } else {
                // العرض السياقي: جلب سجلات من company_user
                if (!$activeCompanyId && !$isSuperAdmin) {
                    return api_forbidden('يجب تحديد شركة نشطة.');
                }

                $query = CompanyUser::with([
                    'user' => fn($q) => $q->with(['creator', 'companies.logo']),
                    'company',
                ]);

                if (!$isSuperAdmin) {
                    $query->where('company_id', $activeCompanyId);

                    if (!$isCompanyAdmin && !$canViewAll) {
                        if ($canViewChildren) {
                            $descendantUserIds = $authUser->getDescendantUserIds();
                            $query->whereIn('user_id', $descendantUserIds);
                        } else {
                            return api_forbidden('ليس لديك صلاحية لعرض المستخدمين.');
                        }
                    }
                }

                // استبعاد المستخدم الحالي من القائمة الإدارية
                $query->where('user_id', '!=', $authUser->id);
            }

            // تطبيق الفلاتر (نفس المنطق للجهتين مع اختلاف الحقول)
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search, $isGlobalView) {
                    if ($isGlobalView) {
                        $q->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    } else {
                        $q->where('nickname_in_company', 'like', "%{$search}%")
                            ->orWhere('full_name_in_company', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    }
                });
            }

            // فلترة حسب نوع العميل
            if ($request->filled('customer_type')) {
                $customerType = $request->input('customer_type');
                if ($isGlobalView) {
                    $query->whereHas('companyUsers', function ($q) use ($customerType, $activeCompanyId) {
                        $q->where('customer_type_in_company', $customerType);
                        if ($activeCompanyId) {
                            $q->where('company_id', $activeCompanyId);
                        }
                    });
                } else {
                    $query->where('customer_type_in_company', $customerType);
                }
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by');
            if (empty($sortField)) {
                $sortField = $isGlobalView ? 'created_at' : 'sales_count';
            }
            $sortOrder = $request->input('sort_order', 'desc');

            $query->orderBy($sortField, $sortOrder);
            if ($sortField !== 'created_at') {
                $query->orderBy('created_at', 'desc');
            }

            $data = $query->paginate($perPage);

            $resourceClass = $isGlobalView ? UserResource::class : CompanyUserBasicResource::class;

            return api_success($resourceClass::collection($data), 'تم جلب البيانات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * البحث عن مستخدم موجود مسبقاً في النظام (بناءً على الهاتف أو الإيميل)
     * 
     * @queryParam phone string رقم الهاتف للبحث.
     * @queryParam email string البريد الإلكتروني للبحث.
     */
    public function lookup(Request $request)
    {
        try {
            $request->validate([
                'phone' => 'nullable|string',
                'email' => 'nullable|string|email',
            ]);

            if (!$request->filled('phone') && !$request->filled('email')) {
                return api_error('يجب إدخال رقم الهاتف أو البريد الإلكتروني للبحث.', [], 400);
            }

            $query = User::withoutGlobalScope('company');

            $query->where(function ($q) use ($request) {
                if ($request->filled('phone')) {
                    $q->where('phone', $request->phone);
                }
                if ($request->filled('email')) {
                    if ($request->filled('phone')) {
                        $q->orWhere('email', $request->email);
                    } else {
                        $q->where('email', $request->email);
                    }
                }
            });

            $user = $query->with([
                'companies' => function ($q) {
                    $q->select('companies.id', 'companies.name');
                }
            ])->first();

            if (!$user) {
                return api_success(null, 'المستخدم غير موجود مسبقاً.');
            }

            return api_success([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'nickname' => $user->nickname,
                'full_name' => $user->full_name,
                'avatar_url' => $user->avatar_url,
                'companies' => $user->companies->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_in_current_company' => $c->id == Auth::user()->company_id
                ])
            ], 'تم العثور على بيانات المستخدم في النظام.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * إضافة مستخدم جديد
     * 
     * @bodyParam phone string required رقم الهاتف.
     * @bodyParam nickname string اللقب.
     */
    public function store(UserRequest $request)
    {
        $authUser = Auth::user();
        Log::info('=== USER CREATION START ===', ['auth_user' => $authUser->id, 'company' => $authUser->company_id, 'request' => $request->all()]);

        if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('users.create')])) {
            Log::warning('Unauthorized user creation attempt', ['user_id' => $authUser->id]);
            return api_forbidden();
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();
            $activeCompanyId = $authUser->company_id;
            Log::info('Step 1: Validated data', ['phone' => $validatedData['phone'], 'company_id' => $activeCompanyId]);

            // البحث عن مستخدم موجود مسبقاً في النظام بالكامل
            $userQuery = User::withoutGlobalScope('company');

            $user = $userQuery->where(function ($q) use ($validatedData) {
                $q->where('phone', $validatedData['phone']);
                if (!empty($validatedData['email'])) {
                    $q->orWhere('email', $validatedData['email']);
                }
            })->first();

            if ($user) {
                // إذا كان المستخدم موجوداً، نتحقق من عدم ارتباطه مسبقاً بنفس الشركة
                $companyUser = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $activeCompanyId)
                    ->first();

                if ($companyUser) {
                    Log::warning('CONFLICT: User already exists in company', [
                        'user_id' => $user->id,
                        'company_id' => $activeCompanyId,
                        'company_user_id' => $companyUser->id,
                        'phone' => $validatedData['phone']
                    ]);
                    DB::rollback();
                    return api_error('هذا المستخدم مرتبط مسبقاً بهذه الشركة.', [
                        'user_id' => $user->id,
                        'phone' => $validatedData['phone']
                    ], 409);
                }
                Log::info('Existing user found - will link to current company');
            } else {
                Log::info('Step 2: User not found - creating new user');
                // إنشاء مستخدم عالمي جديد
                $user = User::create([
                    'username' => $validatedData['username'] ?? $validatedData['phone'],
                    'email' => $validatedData['email'] ?? null,
                    'phone' => $validatedData['phone'],
                    'password' => $validatedData['password'] ?? 'password', // الافتراضي إذا لم يُحدد
                    'created_by' => $authUser->id,
                    'company_id' => $activeCompanyId, // الشركة النشطة عند الإنشاء
                    'full_name' => $validatedData['full_name'],
                    'nickname' => $validatedData['nickname'],
                ]);
                Log::info('New user created', ['user_id' => $user->id]);
            }

            // إنشاء أو تحديث سجل العلاقة مع الشركة (Contextual Data)
            $companyUser = CompanyUser::updateOrCreate([
                'user_id' => $user->id,
                'company_id' => $activeCompanyId,
            ], [
                'nickname_in_company' => $validatedData['nickname'] ?? $user->nickname,
                'full_name_in_company' => $validatedData['full_name'] ?? $user->full_name,
                'balance_in_company' => $validatedData['balance'] ?? 0,
                'customer_type_in_company' => $validatedData['customer_type'] ?? 'default',
                'status' => $validatedData['status'] ?? 'active',
                'created_by' => $authUser->id,
            ]);
            Log::info('CompanyUser record established', ['company_user_id' => $companyUser->id]);

            // مزامنة الشركات الإضافية (إذا وجدت)
            if ($request->has('company_ids') && !empty($request->input('company_ids'))) {
                $companyIds = (array) $request->input('company_ids');
                if ($activeCompanyId && !in_array($activeCompanyId, $companyIds)) {
                    $companyIds[] = $activeCompanyId;
                }
                $companyIds = array_unique(array_filter($companyIds));

                $isSuperAdmin = $authUser->can(perm_key('admin.super'));
                $isCompanyAdmin = $authUser->can(perm_key('admin.company'));

                Log::info('Syncing additional companies', ['company_ids' => $companyIds]);

                if ($isSuperAdmin) {
                    $user->companies()->syncWithoutDetaching($companyIds);
                } elseif ($isCompanyAdmin) {
                    $myCompanyIds = $authUser->companies()->pluck('companies.id')->toArray();
                    $allowedCompanyIds = array_intersect($companyIds, $myCompanyIds);

                    $user->companies()->syncWithPivotValues($allowedCompanyIds, [
                        'created_by' => $authUser->id,
                        'status' => 'active'
                    ], false);
                }
            }

            // التأكد من وجود الخزنة لجميع الشركات المرتبطة بالمستخدم
            $user->ensureCashBoxesForAllCompanies();


            if ($request->has('images_ids')) {
                $user->syncImages($request->input('images_ids'), 'avatar');
            }

            // تعيين الأدوار والصلاحيات الأولية
            if ($activeCompanyId) {
                $isSystemAdmin = $authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')]);

                if ($request->has('roles')) {
                    $requestedRoles = (array) $validatedData['roles'];
                    if (!$isSystemAdmin) {
                        $myRoles = $authUser->getRoleNames()->toArray();
                        $unauthorizedRoles = array_diff($requestedRoles, $myRoles);
                        if (!empty($unauthorizedRoles)) {
                            return api_forbidden('لا يمكنك منح أدوار لا تملكها: ' . implode(', ', $unauthorizedRoles));
                        }
                    }
                    $user->syncRoles($requestedRoles);
                }

                if ($request->has('permissions')) {
                    $requestedPermissions = (array) $validatedData['permissions'];
                    if (!$isSystemAdmin) {
                        $myPermissions = $authUser->getAllPermissions()->pluck('name')->toArray();
                        $unauthorizedPermissions = array_diff($requestedPermissions, $myPermissions);
                        if (!empty($unauthorizedPermissions)) {
                            return api_forbidden('لا يمكنك منح صلاحيات لا تملكها: ' . implode(', ', $unauthorizedPermissions));
                        }
                    }
                    $user->syncPermissions($requestedPermissions);
                }
            }

            DB::commit();
            Log::info('=== USER CREATION SUCCESS ===', ['user_id' => $user->id]);

            return api_success(new CompanyUserResource($companyUser->load('user', 'company')), 'تمت إضافة المستخدم بنجاح.');

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('USER CREATION FAILED', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            return api_error('حدث خطأ أثناء إضافة المستخدم: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * عرض تفاصيل مستخدم
     * 
     * @urlParam user required معرف المستخدم. Example: 1
     * 
     * @apiResource App\Http\Resources\User\UserResource
     * @apiResourceModel App\Models\User
     */
    public function show(User $user, Request $request)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $activeCompanyId = $authUser->company_id;
        $isSuperAdmin = $authUser->can(perm_key('admin.super'));
        $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
        $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
        $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));

        $useBasicResource = filter_var($request->input('basic', true), FILTER_VALIDATE_BOOLEAN);

        if ($isSuperAdmin) {
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        if ($authUser->id === $user->id && $canViewSelf) {
            $companyUser = $user->activeCompanyUser()
                ->with([
                    'user.cashBoxes' => function ($q) use ($activeCompanyId) {
                        $q->where('company_id', $activeCompanyId);
                    }
                ])
                ->first();

            if ($companyUser) {
                if ($useBasicResource) {
                    return api_success(CompanyUserBasicResource::make($companyUser), 'تم جلب بيانات المستخدم بنجاح.');
                } else {
                    $companyUser->load([
                        'user.cashBoxes' => function ($q) use ($activeCompanyId) {
                            $q->where('company_id', $activeCompanyId);
                        },
                        'user.creator',
                        'company'
                    ]);
                    return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح.');
                }
            }
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        if (($authUser->hasPermissionTo(perm_key('admin.company')) || $canViewAll) && $activeCompanyId) {
            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->with([
                    'user.cashBoxes' => function ($q) use ($activeCompanyId) {
                        $q->where('company_id', $activeCompanyId);
                    },
                    'user.creator',
                    'company'
                ])
                ->first();

            if (!$companyUser) {
                return api_not_found('المستخدم غير موجود أو ليس لديه علاقة بالشركة النشطة.');
            }

            if ($useBasicResource) {
                return api_success(CompanyUserBasicResource::make($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            } else {
                return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            }
        }

        if ($canViewChildren && $activeCompanyId) {
            $descendantUserIds = $authUser->getDescendantUserIds();

            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->whereIn('user_id', $descendantUserIds)
                ->with([
                    'user.cashBoxes' => function ($q) use ($activeCompanyId) {
                        $q->where('company_id', $activeCompanyId);
                    },
                    'user.creator',
                    'company'
                ])
                ->first();

            if (!$companyUser) {
                return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم أو المستخدم غير مرتبط بالشركة النشطة.');
            }

            if ($useBasicResource) {
                return api_success(CompanyUserBasicResource::make($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            } else {
                return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            }
        }

        // Fallback for Admins: If they can't find it in the current company context, try to see if it's the User itself
        // but it should have been caught by the self-view or admin.super check.
        // We return UserResource if they have view_all but the user is not in the active company.
        if ($canViewAll) {
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح (عرض عام).');
        }

        return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم.');
    }
    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * تحديث بيانات مستخدم
     * 
     * @urlParam user required معرف المستخدم. Example: 1
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = Auth::user();
        if (!$authUser)
            return api_unauthorized();

        $isSuperAdmin = $authUser->can(perm_key('admin.super'));
        $activeCompanyId = $authUser->company_id;

        DB::beginTransaction();
        try {
            $validated = $request->validated();

            // 1. تحديث البيانات العالمية (جدول users)
            // يُسمح للسوبر أدمن أو للمستخدم نفسه (تحديث بروفايله)
            $isUpdatingSelf = ($authUser->id === $user->id);
            if ($isSuperAdmin || $isUpdatingSelf) {
                $userData = array_intersect_key($validated, array_flip([
                    'username',
                    'email',
                    'phone',
                    'full_name',
                    'nickname',
                    'password',
                    'position',
                    'settings'
                ]));
                if (!empty($userData)) {
                    $user->update($userData);
                }
            }

            // [NEW] Sync Companies (Super Admin or Company Admin with scoping)
            if ($request->has('company_ids')) {
                $companyIds = (array) $request->input('company_ids');
                if ($activeCompanyId && !in_array($activeCompanyId, $companyIds)) {
                    $companyIds[] = $activeCompanyId;
                }
                $companyIds = array_unique(array_filter($companyIds));

                $isCompanyAdmin = $authUser->can(perm_key('admin.company'));

                if ($isSuperAdmin) {
                    $user->companies()->sync($companyIds);
                } elseif ($isCompanyAdmin) {
                    // Company Admin can only sync companies they themselves belong to
                    $myCompanyIds = $authUser->companies()->pluck('companies.id')->toArray();
                    $allowedCompanyIds = array_intersect($companyIds, $myCompanyIds);

                    // We use syncWithoutDetaching or a manual sync to ensure we don't accidentally
                    // remove links to companies the admin DOESN'T manage.
                    $user->companies()->syncWithPivotValues($allowedCompanyIds, [
                        'created_by' => $authUser->id,
                        'status' => 'active'
                    ], false); // false = don't detach others (very important for company admins)
                }
            }

            // 2. تحديث البيانات السياقية (جدول company_user)
            if ($activeCompanyId) {
                $companyUser = $user->companyUsers()->where('company_id', $activeCompanyId)->first();
                if ($companyUser) {
                    $contextData = [];
                    if (isset($validated['nickname']))
                        $contextData['nickname_in_company'] = $validated['nickname'];
                    if (isset($validated['full_name']))
                        $contextData['full_name_in_company'] = $validated['full_name'];
                    if (isset($validated['status']))
                        $contextData['status'] = $validated['status'];
                    if (isset($validated['balance']))
                        $contextData['balance_in_company'] = $validated['balance'];
                    if (isset($validated['customer_type']))
                        $contextData['customer_type_in_company'] = $validated['customer_type'];
                    if (isset($validated['position']))
                        $contextData['position_in_company'] = $validated['position'];

                    if (!empty($contextData)) {
                        $companyUser->update($contextData);
                    }
                }
            }

            if ($request->has('images_ids')) {
                $user->syncImages($request->input('images_ids'), 'avatar');
            }

            // 3. تحديث الأدوار والصلاحيات (سياق الشركة الحالية)
            if ($activeCompanyId && !$isUpdatingSelf) {
                $isSystemAdmin = $isSuperAdmin || $authUser->can(perm_key('admin.company'));

                if ($request->has('roles')) {
                    $requestedRoles = $validated['roles'];
                    if (!$isSystemAdmin) {
                        $myRoles = $authUser->getRoleNames()->toArray();
                        $unauthorizedRoles = array_diff($requestedRoles, $myRoles);
                        if (!empty($unauthorizedRoles)) {
                            return api_forbidden('لا يمكنك منح أدوار لا تملكها: ' . implode(', ', $unauthorizedRoles));
                        }
                    }
                    $user->syncRoles($requestedRoles);
                }

                if ($request->has('permissions')) {
                    $requestedPermissions = $validated['permissions'];
                    if (!$isSystemAdmin) {
                        $myPermissions = $authUser->getAllPermissions()->pluck('name')->toArray();
                        $unauthorizedPermissions = array_diff($requestedPermissions, $myPermissions);
                        if (!empty($unauthorizedPermissions)) {
                            return api_forbidden('لا يمكنك منح صلاحيات لا تملكها: ' . implode(', ', $unauthorizedPermissions));
                        }
                    }
                    $user->syncPermissions($requestedPermissions);
                }
            }

            DB::commit();
            return api_success(new UserResource($user->load('activeCompanyUser.company', 'companies')), 'تم تحديث البيانات بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * حذف مستخدمين (Batch)
     * 
     * @bodyParam item_ids integer[] required مصفوفة المعرفات. Example: [2, 3]
     */
    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $userIds = $request->input('item_ids');
        if (!$userIds || !is_array($userIds) || empty($userIds)) {
            return api_error('لم يتم تحديد معرفات المستخدمين بشكل صحيح', [], 400);
        }

        DB::beginTransaction();
        try {
            $usersToDelete = User::whereIn('id', $userIds)->get();
            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
            $canDeleteAll = $authUser->hasPermissionTo(perm_key('users.delete_all'));
            $canDeleteChildren = $authUser->hasPermissionTo(perm_key('users.delete_children'));

            $deletedCount = 0;
            $skippedCount = 0;
            $skipReasons = []; // **[تعديل]: مصفوفة لتخزين أسباب التخطي**
            $descendantUserIds = [];

            if ($canDeleteChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
            }

            foreach ($usersToDelete as $user) {
                if ($user->id === $authUser->id) {
                    continue;
                }

                // 1. منطق المشرف العام (Hard Delete من النظام)
                if ($isSuperAdmin || $canDeleteAll) {

                    // يتم هنا افتراض أن المشرف العام له صلاحية تجاوز التحقق من سلامة البيانات
                    // أو أن يتم إضافة فحص مشابه لـ hasActiveTransactionsInCompany على مستوى النظام كله هنا إذا لزم الأمر.

                    $user->cashBoxes()->delete();
                    $user->companyUsers()->delete();
                    $user->delete();
                    $deletedCount++;
                }

                // 2. منطق مسؤول الشركة (Un-link من الشركة)
                elseif ($activeCompanyId && ($isCompanyAdmin || $canDeleteChildren)) {
                    if ($isCompanyAdmin || ($canDeleteChildren && in_array($user->id, $descendantUserIds))) {

                        // **[تعديل]: استدعاء الدالة واستقبال رسالة المنع**
                        $deletionSafetyCheck = $user->hasActiveTransactionsInCompany($activeCompanyId);

                        if ($deletionSafetyCheck !== null) {
                            $skippedCount++;
                            // **[تعديل]: تسجيل سبب التخطي لإرساله للواجهة الأمامية**
                            $skipReasons[] = [
                                'user_id' => $user->id,
                                'username' => $user->username,
                                'reason' => $deletionSafetyCheck['message'],
                            ];
                            Log::warning("تم تخطي فصل المستخدم {$user->id} ({$user->username}) بسبب: {$deletionSafetyCheck['message']}");
                            continue;
                        }

                        $companyUser = $user->companyUsers()->where('company_id', $activeCompanyId)->first();

                        if ($companyUser) {
                            // **[تنظيف]:** تم حذف السطر القديم لحذف الخزنة يدوياً

                            $companyUser->delete();
                            $deletedCount++;

                            // الحذف النهائي المشروط للمستخدم من جدول users إذا لم يعد لديه ارتباطات
                            if ($user->companyUsers()->count() === 0) {
                                $user->delete();
                            }
                        }
                    }
                }
            }

            // **[منطق إرجاع الخطأ]:** إذا لم يتم حذف أي مستخدم (سواء بسبب الصلاحيات أو التخطي)
            if ($deletedCount === 0) {
                DB::rollBack();
                $message = 'لم يتم حذف أي مستخدمين.';
                $data = []; // البيانات التي سترجع للواجهة الأمامية

                if ($skippedCount > 0) {
                    $message .= " تم تخطي {$skippedCount} مستخدم لوجود سجلات حركية/مالية مرتبطة بالشركة النشطة، ولا يمكن فصلهم.";
                    $data['skipped_users'] = $skipReasons;
                } else {
                    $message .= " تحقق من الصلاحيات أو معرفات المستخدمين.";
                }

                return api_error($message, $data, 403);
            }

            // **[منطق إرجاع النجاح]:** عند نجاح عملية الحذف
            DB::commit();
            $data = ($skippedCount > 0) ? ['skipped_users' => $skipReasons] : [];
            $message = "تم معالجة حذف {$deletedCount} مستخدم بنجاح" . ($skippedCount > 0 ? " مع تخطي {$skippedCount} مستخدم لوجود سجلات حركية." : "");

            return api_success($data, $message);
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل حذف المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'item_ids' => $userIds]);
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * تغيير الشركة النشطة
     * 
     * @urlParam user required معرف المستخدم. Example: 1
     * @bodyParam company_id integer required معرف الشركة. Example: 2
     */
    public function changeCompany(Request $request, User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        if (
            !$authUser->hasPermissionTo(perm_key('admin.super')) &&
            !$authUser->hasPermissionTo(perm_key('users.update_all')) &&
            $authUser->id !== $user->id
        ) {
            return api_forbidden('ليس لديك صلاحية لتغيير شركة هذا المستخدم.');
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        DB::beginTransaction();
        try {
            $newCompanyId = $validated['company_id'];

            $companyUserExists = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $newCompanyId)
                ->exists();

            if (!$companyUserExists) {
                DB::rollback();
                return api_error('المستخدم غير مرتبط بالشركة المحددة.', [], 400);
            }

            $user->update(['company_id' => $newCompanyId]);

            $user->load('activeCompanyUser.company');
            DB::commit();

            return api_success(new UserResource($user), 'تم تغيير الشركة النشطة للمستخدم بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل تغيير شركة المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'target_user_id' => $user->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * البحث عن المستخدمين بناءً على الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function usersSearch(Request $request)
    {
        $authUser = Auth::user();

        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }

            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
            $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));

            $baseQuery = CompanyUser::query();

            $baseQuery->with([
                'user' => fn($q) => $q->with([
                    'cashBoxes' => function ($cashBoxQuery) use ($activeCompanyId) {
                        if ($activeCompanyId) {
                            $cashBoxQuery->where('company_id', $activeCompanyId);
                        }
                    },
                    'creator',
                    'companies.logo'
                ]),
                'company',
            ]);

            if ($isSuperAdmin) {
                // يرى الكل
            } elseif ($activeCompanyId) {
                $baseQuery->where('company_id', $activeCompanyId);

                if ($isCompanyAdmin || $canViewAll) {
                    // يرى الكل في شركته
                } elseif ($canViewChildren) {
                    $descendantUserIds = $authUser->getDescendantUserIds();
                    $baseQuery->whereIn('user_id', $descendantUserIds);
                } elseif ($canViewSelf) {
                    $baseQuery->where('user_id', $authUser->id);
                } else {
                    return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين في هذه الشركة.');
                }
            } else {
                return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين.');
            }

            $perPage = max(1, $request->input('per_page', 10));
            $page = max(1, $request->input('page', 1));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $search = $request->input('search');
            $baseQueryWithoutSearch = clone $baseQuery;

            if ($request->filled('search')) {
                $baseQuery->where(function ($subQuery) use ($search) {
                    $subQuery->where('nickname_in_company', 'like', '%' . $search . '%')
                        ->orWhere('full_name_in_company', 'like', '%' . $search . '%')
                        ->orWhere('user_phone', 'like', '%' . $search . '%');
                });
            }

            $baseQuery
                ->when($request->filled('nickname'), fn($q) =>
                    $q->where('nickname_in_company', 'like', '%' . $request->nickname . '%'))
                ->when($request->filled('email'), fn($q) =>
                    $q->whereHas('user', fn($u) =>
                        $u->where('email', 'like', '%' . $request->email . '%')))
                ->when($request->filled('phone'), fn($q) =>
                    $q->whereHas('user', fn($u) =>
                        $u->where('phone', 'like', '%' . $request->phone . '%')))
                ->when($request->filled('status'), fn($q) =>
                    $q->where('status', $request->input('status')))
                ->when($request->filled('created_at_from'), fn($q) =>
                    $q->where('company_user.created_at', '>=', $request->input('created_at_from') . ' 00:00:00'))
                ->when($request->filled('created_at_to'), fn($q) =>
                    $q->where('company_user.created_at', '<=', $request->input('created_at_to') . ' 23:59:59'));

            if (in_array($sortField, ['nickname_in_company', 'status', 'balance_in_company', 'position_in_company', 'customer_type_in_company', 'full_name_in_company', 'user_phone', 'user_email', 'user_username'])) {
                $baseQuery->orderBy('company_user.' . $sortField, $sortOrder);
            } elseif (in_array($sortField, ['username', 'email', 'phone'])) {
                $baseQuery->join('users', 'company_user.user_id', '=', 'users.id')
                    ->orderBy('users.' . $sortField, $sortOrder)
                    ->select('company_user.*');
            } else {
                $baseQuery->orderBy('company_user.id', $sortOrder);
            }

            $companyUsers = $baseQuery->paginate($perPage);

            if ($companyUsers->isEmpty() && $request->filled('search')) {
                $allCompanyUsers = (clone $baseQueryWithoutSearch)->limit(500)->get();

                $paginated = smart_search_paginated(
                    $allCompanyUsers,
                    $search,
                    ['nickname_in_company', 'full_name_in_company', 'user_phone'],
                    $request->query(),
                    null,
                    $perPage,
                    $page
                );

                Log::debug("✅ عدد النتائج الذكية بعد الترتيب: " . $paginated->total());

                return api_success(CompanyUserBasicResource::collection($paginated), 'تم إرجاع نتائج مقترحة بناءً على البحث.');
            }

            if ($companyUsers->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين.');
            }

            return api_success(CompanyUserBasicResource::collection($companyUsers), 'تم جلب المستخدمين بنجاح.');
        } catch (Throwable $e) {
            Log::error("فشل البحث عن المستخدمين: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id ?? null,
                'request_data' => $request->all()
            ]);

            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * إحصائيات المستخدمين
     */
    public function stats(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            if (!$authUser)
                return api_unauthorized();

            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isGlobal = filter_var($request->input('global', false), FILTER_VALIDATE_BOOLEAN) && $isSuperAdmin;

            $query = CompanyUser::query();

            if (!$isGlobal && $activeCompanyId) {
                $query->where('company_id', $activeCompanyId);
            }

            $total = (clone $query)->count();
            $active = (clone $query)->where('status', 'active')->count();
            $inactive = (clone $query)->where('status', 'inactive')->count();

            // Admins (has any admin role)
            $admins = (clone $query)->whereHas('user.roles', function ($q) {
                $q->whereIn('name', ['admin.super', 'admin.company']);
            })->count();

            return api_success([
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'admins' => $admins,
            ]);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
