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
     * عرض قائمة المستخدمين بناءً على الصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function index(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }

            $activeCompanyId = $authUser->company_id;
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
            $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));

            // 1. فحص الصلاحية الأولية (هل لديه أي صلاحية رؤية تبرر إكمال الطلب؟)
            if (
                !$isSuperAdmin &&
                (!$isCompanyAdmin || !$activeCompanyId) &&
                !$canViewAll &&
                !$canViewChildren
                // تم استبعاد $canViewSelf من هذا الفحص لتجنب حالة أن يكون الـ AuthUser هو المستخدم الوحيد ويريد رؤية بياناته الخاصة (لكنه لا يُسمح له هنا برؤية قائمة الآخرين).
            ) {
                // إذا لم يكن لديه أي من صلاحيات الرؤية الجماعية، نمنعه.
                return api_forbidden('ليس لديك صلاحية لعرض قائمة المستخدمين الآخرين.');
            }

            $query = CompanyUser::with([
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

            // **[تأكيد المنطق]: استبعاد المستخدم الموثق من القائمة المعروضة للإدارة**
            $query->where('user_id', '!=', $authUser->id);

            // 2. تطبيق منطق الصلاحيات على الاستعلام
            if ($isSuperAdmin) {
                // المدير العام يرى كل المستخدمين في كل الشركات
            } elseif ($activeCompanyId) {
                if ($isCompanyAdmin || $canViewAll) {
                    // يرى الجميع في الشركة النشطة
                    $query->where('company_id', $activeCompanyId);
                } elseif ($canViewChildren) {
                    // يرى التابعين له في الشركة النشطة
                    $descendantUserIds = $authUser->getDescendantUserIds();

                    // تأكيد: يجب أن نرى فقط المستخدمين التابعين له والموجودين في الشركة النشطة
                    $query->where('company_id', $activeCompanyId)
                        ->whereIn('user_id', $descendantUserIds);

                } elseif ($canViewSelf) {
                    // **[مراجعة]: بما أننا استبعدنا الـ AuthUser، وصلاحية view_self لا تمنح رؤية للآخرين، يجب أن تظهر له القائمة فارغة أو نمنعه.**
                    // المنطق الحالي يمنع الوصول إذا كانت هذه هي الصلاحية الوحيدة المتبقية (وهو المنطق السليم لقائمة إدارية):
                    return api_forbidden('ليس لديك صلاحية لعرض المستخدمين الآخرين في هذه الشركة.');
                }
            } else {
                // لا يوجد شركة نشطة
                return api_forbidden('لا توجد شركة نشطة مرتبطة بك للبحث ضمن نطاقها.');
            }

            // تطبيق فلاتر البحث (كما هي)
            if ($request->filled('nickname')) {
                $query->where('nickname_in_company', 'like', '%' . $request->input('nickname') . '%');
            }
            if ($request->filled('email')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('email', 'like', '%' . $request->input('email') . '%');
                });
            }
            if ($request->filled('phone')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('phone', 'like', '%' . $request->input('phone') . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('created_at_from')) {
                $query->where('company_user.created_at', '>=', $request->input('created_at_from') . ' 00:00:00');
            }
            if ($request->filled('created_at_to')) {
                $query->where('company_user.created_at', '<=', $request->input('created_at_to') . ' 23:59:59');
            }

            // الفرز والتصفح (كما هي)
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            if (in_array($sortField, ['nickname_in_company', 'status', 'balance_in_company'])) {
                $query->orderBy($sortField, $sortOrder);
            } elseif (in_array($sortField, ['user_phone', 'user_email', 'user_username'])) {
                $query->join('users', 'company_user.user_id', '=', 'users.id')
                    ->orderBy('users.' . str_replace('user_', '', $sortField), $sortOrder)
                    ->select('company_user.*');
            } else {
                $query->orderBy('company_user.id', $sortOrder);
            }

            $companyUsers = $query->paginate($perPage);

            // تحديد الـ Resource المطلوب
            $full = filter_var(
                $request->input('full', false),
                FILTER_VALIDATE_BOOLEAN
            );
            $resourceClass = $full
                ? CompanyUserResource::class
                : CompanyUserBasicResource::class;


            if ($companyUsers->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين.');
            } else {
                return api_success($resourceClass::collection($companyUsers), 'تم جلب المستخدمين بنجاح.');
            }
        } catch (Throwable $e) {
            Log::error("فشل جلب قائمة المستخدمين: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * إنشاء مستخدم جديد.
     *
     * @param UserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request)
    {
        $authUser = Auth::user();

        if (
            !$authUser || (
                !$authUser->hasAnyPermission(perm_key('admin.super')) &&
                !$authUser->hasAnyPermission(perm_key('users.create')) &&
                !$authUser->hasAnyPermission(perm_key('admin.company'))
            )
        ) {
            return api_forbidden('ليس لديك صلاحية لإنشاء مستخدمين.');
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();
            $activeCompanyId = $authUser->company_id;

            if (!$authUser->hasAnyPermission(perm_key('admin.super')) && !$activeCompanyId) {
                DB::rollback();
                return api_forbidden('لإنشاء مستخدمين، يجب أن تكون مرتبطًا بشركة نشطة.');
            }

            $user = null;
            if (!empty($validatedData['phone'])) {
                $user = User::where('phone', $validatedData['phone'])->first();
            }

            if (!$user && !empty($validatedData['email'])) {
                $user = User::where('email', $validatedData['email'])->first();
            }

            if (!$user) {
                $userDataForUserTable = [
                    'username' => $validatedData['username'],
                    'email' => $validatedData['email'],
                    'phone' => $validatedData['phone'],
                    'password' => $validatedData['password'],
                    'created_by' => $authUser->id,
                    'company_id' => $activeCompanyId,
                    'full_name' => $validatedData['full_name'] ?? null,
                    'nickname' => $validatedData['nickname'] ?? null,
                ];
                $user = User::create($userDataForUserTable);
                Log::info('New User created in users table.', ['user_id' => $user->id]);
            } else {
                $companyUserExists = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $activeCompanyId)
                    ->exists();

                Log::info('Existing User found. Checking company_user relation.', [
                    'user_id' => $user->id,
                    'active_company_id' => $activeCompanyId,
                    'company_user_exists' => $companyUserExists
                ]);

                if ($companyUserExists) {
                    DB::rollback();
                    return api_error('هذا المستخدم موجود بالفعل في الشركة النشطة.', [], 409);
                }

                if (!$authUser->hasAnyPermission(perm_key('admin.super'))) {
                    $user->update(['company_id' => $activeCompanyId]);
                    Log::info('Updated user main company_id for existing user (non-super admin).', ['user_id' => $user->id, 'new_company_id' => $activeCompanyId]);
                }
            }

            $companyUserData = [
                'user_id' => $user->id,
                'company_id' => $activeCompanyId,
                'nickname_in_company' => $validatedData['nickname'] ?? $user->username,
                'full_name_in_company' => $validatedData['full_name'] ?? $user->full_name,
                'balance_in_company' => $validatedData['balance'] ?? 0,
                'customer_type_in_company' => $validatedData['customer_type'] ?? 'default',
                'status' => $validatedData['status'] ?? 'active',
                'position_in_company' => $validatedData['position'] ?? null,
                'created_by' => $authUser->id,
                'user_phone' => $user->phone,
                'user_email' => $user->email,
                'user_username' => $user->username,
            ];

            Log::info('Base CompanyUser data prepared.', ['data' => $companyUserData]);

            $companyUser = null;

            if (($authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('users.update_all')]) && array_key_exists('company_ids', $validatedData))) {
                Log::info('Admin/Super Admin/UpdateAll handling company_ids for user creation.', ['user_id' => $user->id, 'company_ids_from_request' => $validatedData['company_ids']]);

                $companyIdsFromRequest = collect($validatedData['company_ids'])
                    ->filter(fn($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
                    ->values()
                    ->toArray();

                foreach ($companyIdsFromRequest as $companyId) {
                    // هذا السطر يطلق المراقب (created إذا كان جديداً، updated إذا كان موجوداً)
                    $currentCompanyUser = CompanyUser::updateOrCreate(
                        ['user_id' => $user->id, 'company_id' => $companyId],
                        array_merge($companyUserData, ['company_id' => $companyId])
                    );
                    Log::info('CompanyUser relation updated/created (from company_ids loop).', ['user_id' => $user->id, 'company_id' => $companyId, 'company_user_id' => $currentCompanyUser->id]);
                    if ($companyId == $activeCompanyId) {
                        $companyUser = $currentCompanyUser;
                    }
                }

                if (!$companyUser && $activeCompanyId) {
                    $companyUser = CompanyUser::where('user_id', $user->id)
                        ->where('company_id', $activeCompanyId)
                        ->first();
                    Log::info('Fetched active company user outside of company_ids loop.', ['user_id' => $user->id, 'company_id' => $activeCompanyId]);
                }
            } else {
                Log::info('Creating single CompanyUser record for active company.', ['user_id' => $user->id, 'active_company_id' => $activeCompanyId]);
                // هذا السطر يطلق المراقب (created)
                $companyUser = CompanyUser::create($companyUserData);
                Log::info('Single CompanyUser created for active company.', ['company_user_id' => $companyUser->id]);
            }

            // **[الحذف]** تم حذف السطر الذي يستدعي الدالة القديمة.
            // $user->ensure=CashBoxesForAllCompanies();
            // Log::info('Cash boxes ensured for user companies.', ['user_id' => $user->id]);


            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
                Log::info('Images synced for user.', ['user_id' => $user->id, 'image_ids' => $imagesIds]);
            }

            $user->logCreated('بانشاء المستخدم ' . ($companyUser->nickname_in_company ?? $user->username) . ' في الشركة ' . $companyUser->company->name);
            DB::commit();
            Log::info('User creation transaction committed successfully.', ['user_id' => $user->id]);

            // تحميل العلاقات مع تصفية cashBoxes
            $companyUser->load([
                'user.cashBoxes' => function ($q) use ($activeCompanyId) {
                    // ملاحظة: للتأكد من جلب الخزنة النشطة فقط
                    $q->where('company_id', $activeCompanyId)->where('is_active', true);
                },
                'user.creator',
                'company'
            ]);

            return api_success(new CompanyUserResource($companyUser), 'تم إنشاء المستخدم بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل إنشاء المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }
    /**
     * عرض بيانات مستخدم واحد.
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user, Request $request)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $activeCompanyId = $authUser->company_id;
        $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
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

        return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم.');
    }
    /**
     * تحديث بيانات مستخدم.
     *
     * @param UserUpdateRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $validated = $request->validated();
        $activeCompanyId = $authUser->company_id;

        $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
        $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
        $canUpdateAllUsers = $authUser->hasPermissionTo(perm_key('users.update_all'));
        $canUpdateChildren = $authUser->hasPermissionTo(perm_key('users.update_children'));
        $canUpdateSelf = $authUser->hasPermissionTo(perm_key('users.update_self'));

        DB::beginTransaction();
        try {
            $isUpdatingSelf = ($authUser->id === $user->id);

            $userDataToUpdate = [];
            if (isset($validated['username']))
                $userDataToUpdate['username'] = $validated['username'];
            if (isset($validated['email']))
                $userDataToUpdate['email'] = $validated['email'];
            if (isset($validated['phone']))
                $userDataToUpdate['phone'] = $validated['phone'];
            if (isset($validated['password']))
                $userDataToUpdate['password'] = $validated['password'];
            if (isset($validated['full_name']))
                $userDataToUpdate['full_name'] = $validated['full_name'];
            if (isset($validated['position']))
                $userDataToUpdate['position'] = $validated['position'];
            if (isset($validated['settings']))
                $userDataToUpdate['settings'] = $validated['settings'];
            if (isset($validated['last_login_at']))
                $userDataToUpdate['last_login_at'] = $validated['last_login_at'];
            if (isset($validated['email_verified_at']))
                $userDataToUpdate['email_verified_at'] = $validated['email_verified_at'];

            if ($isUpdatingSelf && $canUpdateSelf) {
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                }
                if ($request->has('images_ids')) {
                    $user->syncImages($request->input('images_ids'), 'avatar');
                }
                $user->logUpdated('بتحديث المستخدم ' . ($user->activeCompanyUser->nickname_in_company ?? $user->username));
                DB::commit();
                Log::info('User self-update transaction committed successfully and function ended.', ['user_id' => $user->id]);

                // تحميل العلاقات مع cashBoxes للشركة النشطة
                $user->load([
                    'cashBoxes' => function ($q) use ($activeCompanyId) {
                        if ($activeCompanyId) {
                            $q->where('company_id', $activeCompanyId);
                        }
                    },
                    'companies.logo',
                    'creator',
                    'companies',
                    'activeCompanyUser.company',
                ]);

                return api_success(new UserResource($user), 'تم تحديث المستخدم بنجاح');
            }

            $companyUserDataToUpdate = [];
            if (isset($validated['nickname']))
                $companyUserDataToUpdate['nickname_in_company'] = $validated['nickname'];
            if (isset($validated['full_name']))
                $companyUserDataToUpdate['full_name_in_company'] = $validated['full_name'];
            if (isset($validated['position']))
                $companyUserDataToUpdate['position_in_company'] = $validated['position'];
            if (isset($validated['customer_type']))
                $companyUserDataToUpdate['customer_type_in_company'] = $validated['customer_type'];
            if (isset($validated['status']))
                $companyUserDataToUpdate['status'] = $validated['status'];
            if (isset($validated['balance']))
                $companyUserDataToUpdate['balance_in_company'] = $validated['balance'];
            if (isset($validated['phone']))
                $companyUserDataToUpdate['user_phone'] = $validated['phone'];
            if (isset($validated['email']))
                $companyUserDataToUpdate['user_email'] = $validated['email'];
            if (isset($validated['username']))
                $companyUserDataToUpdate['user_username'] = $validated['username'];

            Log::info('CompanyUser Data To Update (company_user table) - Base data for syncing:', ['data' => $companyUserDataToUpdate]);

            $canUpdateAnyCompanyUser = false;
            if ($isSuperAdmin || $isCompanyAdmin || $canUpdateAllUsers) {
                $canUpdateAnyCompanyUser = true;
            } elseif ($canUpdateChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $canUpdateAnyCompanyUser = in_array($user->id, $descendantUserIds);
            }

            if ($isSuperAdmin) {
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                }
            } elseif ($canUpdateChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                if (!in_array($user->id, $descendantUserIds)) {
                    DB::rollback();
                    return api_forbidden('ليس لديك صلاحية لتعديل هذا المستخدم.');
                }
            }

            if ($authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')]) && array_key_exists('company_ids', $validated) && !empty($validated['company_ids'])) {
                Log::info('Admin/Super Admin syncing multiple company_ids for user.', ['user_id' => $user->id, 'company_ids_from_request' => $validated['company_ids']]);

                $companyIdsFromRequest = collect($validated['company_ids'])
                    ->filter(fn($id) => !empty($id) && is_numeric($id))
                    ->values()
                    ->toArray();

                if (!empty($companyIdsFromRequest)) {
                    foreach ($companyIdsFromRequest as $companyId) {
                        CompanyUser::updateOrCreate(
                            ['user_id' => $user->id, 'company_id' => $companyId],
                            array_merge($companyUserDataToUpdate, [
                                'created_by' => $authUser->id,
                            ])
                        );
                        Log::info('CompanyUser relation updated/created.', ['user_id' => $user->id, 'company_id' => $companyId]);
                    }
                }
            } elseif ($canUpdateAnyCompanyUser && $activeCompanyId) {
                Log::info('Updating active company user record.', ['user_id' => $user->id, 'active_company_id' => $activeCompanyId]);

                $companyUser = CompanyUser::where('user_id', $user->id)->where('company_id', $activeCompanyId)->first();

                if ($companyUser) {
                    $companyUser->update($companyUserDataToUpdate);
                    Log::info('CompanyUser table (active company) updated successfully.', ['company_user_id' => $companyUser->id, 'updated_fields' => array_keys($companyUserDataToUpdate)]);
                } else {
                    DB::rollback();
                    Log::warning('Forbidden: CompanyUser not found for target user in active company.', ['user_id' => $user->id, 'company_id' => $activeCompanyId]);
                    return api_not_found('المستخدم غير مرتبط بالشركة النشطة لتعديل بياناته.');
                }
            }

            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
                Log::info('Images synced for user.', ['user_id' => $user->id, 'image_ids' => $imagesIds]);
            }

            $user->logUpdated('بتحديث المستخدم ' . ($user->activeCompanyUser->nickname_in_company ?? $user->username));
            DB::commit();
            Log::info('User update transaction committed successfully.', ['user_id' => $user->id]);

            // تحميل العلاقات مع cashBoxes للشركة النشطة
            $user->load([
                'cashBoxes' => function ($q) use ($activeCompanyId) {
                    if ($activeCompanyId) {
                        $q->where('company_id', $activeCompanyId);
                    }
                },
                'companies.logo',
                'creator',
                'companies',
                'activeCompanyUser.company',
            ]);

            return api_success(new UserResource($user), 'تم تحديث المستخدم بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل تحديث المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'target_user_id' => $user->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * حذف المستخدمين.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                    $user->logForceDeleted('المستخدم ' . $user->username);
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
                            $user->logForceDeleted('علاقة المستخدم ' . ($companyUser->nickname_in_company ?? $user->username) . ' بالشركة ' . $companyUser->company->name);
                            $deletedCount++;

                            // الحذف النهائي المشروط للمستخدم من جدول users إذا لم يعد لديه ارتباطات
                            if ($user->companyUsers()->count() === 0) {
                                $user->delete();
                                $user->logForceDeleted('المستخدم ' . $user->username . ' من النظام بعد إزالة جميع ارتباطاته بالشركات.');
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

                return api_forbidden($message, $data, 403);
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
     * تغيير الشركة النشطة للمستخدم.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeCompany(Request $request, User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        if (!$authUser->hasPermissionTo(perm_key('users.update_all')) && $authUser->id !== $user->id) {
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
}
