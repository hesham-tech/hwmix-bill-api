<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Roles\RoleResource;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RoleController extends Controller
{
    /**
     * العلاقات الافتراضية المستخدمة مع الأدوار.
     * @var array
     */
    protected array $relations = [
        'permissions',
        'company',
        'creator'
    ];

    /**
     * @group 09. الأذونات والأمن
     * 
     * عرض قائمة الأدوار
     * 
     * استرجاع كافة المجموعات (Roles) المعرفة في النظام (مثل: مدير، بائع، أمين مخزن).
     * 
     * @queryParam company_id integer فلترة حسب الشركة. Example: 1
     * @queryParam name string البحث باسم الدور. Example: admin
     * @queryParam per_page integer عدد العناصر. Default: 10. Example: -1
     * 
     * @apiResourceCollection App\Http\Resources\Roles\RoleResource
     * @apiResourceModel App\Models\Role
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Role::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // فلترة الأدوار حسب الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأدوار بدون أي قيود
            } elseif ($authUser->hasAnyPermission([perm_key('roles.view_all'), perm_key('admin.company')])) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $query->where('company_id', $companyId);
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $query->where('company_id', $companyId)
                    ->whereIn('created_by', $descendantUserIds);
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $query->where('company_id', $companyId)
                    ->where('created_by', $authUser->id);
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الأدوار.');
            }

            // [جديد] تطبيق القيد الهرمي: غير المدراء لا يرون إلا أدوارهم الخاصة
            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')])) {
                $myRoleNames = $authUser->getRoleNames()->toArray();
                $query->whereIn('name', $myRoleNames);
            }

            if ($request->filled('company_id')) {
                // إذا تم تحديد company_id في الطلب، تأكد من أن المستخدم لديه صلاحية رؤية الأدوار لتلك الشركة
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض الأدوار لشركة أخرى.');
                }
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('role_id')) {
                $query->where('id', $request->input('role_id'));
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }

            $perPage = (int) $request->input('per_page', 10);
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            if ($perPage == -1) {
                // هات كل النتائج بدون باجينيشن
                $roles = $query->get();
            } else {
                // هات النتائج بباجينيشن
                $roles = $query->paginate(max(1, $perPage));
            }
            if ($roles->isEmpty()) {
                return api_success(RoleResource::collection($roles), 'لم يتم العثور على أدوار.');
            } else {
                return api_success(RoleResource::collection($roles), 'تم جلب الأدوار بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 05. إدراة المستخدمين
     * 
     * إنشاء دور جديد
     * 
     * @bodyParam name string required اسم الدور الفريد. Example: Accountant
     * @bodyParam company_ids integer[] مصفوفة الشركات المرتبطة. Example: [1]
     * @bodyParam permissions string[] مصفوفة مسميات الصلاحيات. Example: ["users.view", "invoices.create"]
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (
                !$authUser->hasAnyPermission([
                    perm_key('admin.super'),
                    perm_key('admin.company'),
                    perm_key('roles.create'),
                ])
            ) {
                return api_forbidden('ليس لديك صلاحية لإنشاء الأدوار.');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['nullable', 'string', 'max:255', 'unique:roles,name'],
                'label' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'company_id' => ['sometimes', 'exists:companies,id'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $targetCompanyId = $request->input('company_id', $companyId);

            if ($targetCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('لا يمكنك إنشاء أدوار لشركة أخرى.');
            }

            if (!$targetCompanyId) {
                return api_error('المستخدم غير مرتبط بشركة ولا يمكن إنشاء دور بدون شركة.');
            }

            DB::beginTransaction();
            try {
                // توليد اسم الدور تلقائياً إذا لم يتم إدخاله
                if (empty($validatedData['name'])) {
                    $validatedData['name'] = \Illuminate\Support\Str::slug($validatedData['label'], '_');
                    // التأكد من أن الاسم باللغة الإنجليزية فقط
                    $validatedData['name'] = preg_replace('/[^a-z0-9_]/', '_', strtolower($validatedData['name']));
                    $validatedData['name'] = preg_replace('/_+/', '_', $validatedData['name']); // إزالة الـ underscores المتكررة
                    $validatedData['name'] = trim($validatedData['name'], '_'); // إزالة الـ underscores من البداية والنهاية

                    // التحقق من التفرد
                    $baseName = $validatedData['name'];
                    $counter = 1;
                    while (Role::where('name', $validatedData['name'])->where('company_id', $targetCompanyId)->exists()) {
                        $validatedData['name'] = $baseName . '_' . $counter;
                        $counter++;
                    }
                }

                $roleName = $validatedData['name'];
                $assignedCreatedBy = $authUser->id;

                // [جديد] التحقق الهرمي من الصلاحيات
                if (!empty($validatedData['permissions']) && !$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')])) {
                    $myPermissions = $authUser->getAllPermissions()->pluck('name')->toArray();
                    $unauthorizedPermissions = array_diff($validatedData['permissions'], $myPermissions);

                    if (!empty($unauthorizedPermissions)) {
                        return api_forbidden('لا يمكنك إنشاء دور يحتوي على صلاحيات لا تملكها: ' . implode(', ', $unauthorizedPermissions));
                    }
                }

                $role = Role::firstOrCreate(
                    ['name' => $roleName, 'company_id' => $targetCompanyId],
                    [
                        'guard_name' => 'web',
                        'created_by' => $assignedCreatedBy,
                        'label' => $validatedData['label'] ?? $roleName,
                        'description' => $validatedData['description'] ?? null,
                    ]
                );

                if (!empty($validatedData['permissions'])) {
                    $role->syncPermissions($validatedData['permissions']);
                }

                DB::commit();

                return api_success(new RoleResource($role->load($this->relations)), 'تم إنشاء الدور بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء إنشاء الدور.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 05. إدراة المستخدمين
     * 
     * تحديث بيانات دور
     * 
     * @urlParam role required معرف الدور. Example: 1
     * @bodyParam name string اسم الدور. Example: Senior Accountant
     * @bodyParam permissions string[] تحديث قائمة الصلاحيات. Example: ["invoices.all"]
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // تحميل العلاقات اللازمة للتحقق من الصلاحيات
            $role->load([
                'company' => function ($q) use ($companyId) {
                    $q->where('companies.id', $companyId);
                },
                'creator'
            ]);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_all'))) {
                $canUpdate = $role->company_id == $companyId;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_children'))) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $canUpdate = $role->company_id == $companyId && in_array($role->created_by, $descendantUserIds);
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_self'))) {
                $canUpdate = $role->company_id == $companyId && $role->created_by === $authUser->id;
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذا الدور.');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
                'label' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'company_id' => ['sometimes', 'exists:companies,id'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $validatedData['updated_by'] = $authUser->id;

            // [جديد] التحقق الهرمي من الصلاحيات
            if (!empty($validatedData['permissions']) && !$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')])) {
                $myPermissions = $authUser->getAllPermissions()->pluck('name')->toArray();
                $unauthorizedPermissions = array_diff($validatedData['permissions'], $myPermissions);

                if (!empty($unauthorizedPermissions)) {
                    return api_forbidden('لا يمكنك إعطاء الدور صلاحيات لا تملكها: ' . implode(', ', $unauthorizedPermissions));
                }
            }

            DB::beginTransaction();
            try {
                if (isset($validatedData['name']))
                    $role->name = $validatedData['name'];
                if (isset($validatedData['label']))
                    $role->label = $validatedData['label'];
                if (isset($validatedData['description']))
                    $role->description = $validatedData['description'];

                if (isset($validatedData['company_id'])) {
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $role->company_id = $validatedData['company_id'];
                    } else {
                        return api_forbidden('لا يمكنك تغيير شركة الدور ما لم تكن مسؤولا عاما.');
                    }
                }

                $role->save();

                if (isset($validatedData['permissions']) && is_array($validatedData['permissions'])) {
                    $role->syncPermissions($validatedData['permissions']);
                }

                DB::commit();
                return api_success(new RoleResource($role->load($this->relations)), 'تم تحديث الدور بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الدور.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 05. إدراة المستخدمين
     * 
     * عرض تفاصيل دور
     * 
     * @urlParam role required معرف الدور. Example: 1
     */
    public function show(Role $role): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // تحميل العلاقات اللازمة للتحقق من الصلاحيات
            $role->load(['company', 'creator']);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_all'))) {
                $canView = $role->company_id == $companyId;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $canView = $role->company_id == $companyId && in_array($role->created_by, $descendantUserIds);
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
                $canView = $role->company_id == $companyId && $role->created_by === $authUser->id;
            }

            if ($canView) {
                return api_success(new RoleResource($role->load($this->relations)), 'تم استرداد الدور بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذا الدور.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 05. إدراة المستخدمين
     * 
     * حذف دور واحد أو عدة أدوار (Single/Batch Delete)
     * 
     * @urlParam role integer معرف الدور للحذف المفرد. Example: 1
     * @bodyParam item_ids integer[] مصفوفة معرفات الأدوار للحذف الجماعي. Example: [2, 3]
     */
    public function destroy(Request $request, Role $role = null): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // Determine if single or batch delete
            $roleIds = [];
            if ($role) {
                // Single delete from route parameter
                $roleIds = [$role->id];
            } else {
                // Batch delete from request body
                $roleIds = $request->input('item_ids');
                if (!is_array($roleIds) || empty($roleIds)) {
                    return api_error('معرفات الدور غير صالحة.', [], 400);
                }
            }

            DB::beginTransaction();
            try {
                foreach ($roleIds as $roleId) {
                    $role = Role::find($roleId);

                    if (!$role)
                        continue;

                    $canDelete = false;
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $canDelete = true;
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_all'))) {
                        $canDelete = $role->company_id == $companyId;
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_children'))) {
                        $descendantUserIds = $authUser->getDescendantUserIds();
                        $descendantUserIds[] = $authUser->id;
                        $canDelete = $role->company_id == $companyId && in_array($role->created_by, $descendantUserIds);
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_self'))) {
                        $canDelete = $role->company_id == $companyId && $role->created_by === $authUser->id;
                    }

                    if (!$canDelete) {
                        DB::rollBack();
                        return api_forbidden('ليس لديك صلاحية لحذف الدور: ' . $role->name . ' (المعرف: ' . $role->id . ').');
                    }

                    // التحقق مما إذا كان الدور مرتبطًا بأي مستخدمين
                    $hasUsers = DB::table('model_has_roles')->where('role_id', $role->id)->exists();
                    if ($hasUsers) {
                        DB::rollBack();
                        return api_error('لا يمكن حذف الدور "' . $role->name . '". إنه مرتبط بمستخدم واحد أو أكثر.', [], 409);
                    }

                    $role->delete();
                }

                DB::commit();
                return api_success(null, 'تم حذف الأدوار المحددة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الأدوار.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 05. إدراة المستخدمين
     * 
     * تعيين أدوار لمستخدم
     * 
     * @bodyParam user_id integer required معرف المستخدم. Example: 1
     * @bodyParam roles string[] required مصفوفة مسميات الأدوار. Example: ["admin", "editor"]
     */
    public function assignRole(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (
                !$authUser->hasAnyPermission([
                    perm_key('admin.super'),
                    perm_key('admin.company'),

                ])
            ) {
                return api_forbidden('ليس لديك صلاحية لتعيين الأدوار.');
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'roles' => 'required|array|min:1',
                'roles.*' => 'required|string|exists:roles,name',
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $user = User::with('roles')->findOrFail($validatedData['user_id']);

            // التحقق من صلاحية تعيين الأدوار:
            // 1. لا يمكن للمستخدمين العاديين تعيين أدوار لمستخدمين خارج شركتهم.
            // 2. لا يمكن للمستخدمين العاديين تعيين أدوار لا يملكون صلاحية الوصول إليها.
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                // التحقق من أن المستخدم الذي يتم تعيين الأدوار له ينتمي لنفس الشركة
                if ($user->company_id !== $companyId) {
                    return api_forbidden('لا يمكنك تعيين أدوار لمستخدمين خارج شركتك.');
                }

                // التحقق من أن الأدوار التي يتم تعيينها متاحة للمستخدم الحالي
                $requestedRoleNames = $validatedData['roles'];
                $availableRoles = Role::where('company_id', $companyId)->whereIn('name', $requestedRoleNames)->pluck('name')->toArray();

                $diff = array_diff($requestedRoleNames, $availableRoles);
                if (!empty($diff)) {
                    return api_forbidden('بعض الأدوار المطلوبة غير متاحة لك لتعيينها: ' . implode(', ', $diff));
                }
            }

            DB::beginTransaction();
            try {
                $user->syncRoles($validatedData['roles']); // تعيين جميع الأدوار دفعة واحدة
                DB::commit();
                return api_success([], 'تم تعيين الأدوار للمستخدم بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تعيين الأدوار للمستخدم.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
