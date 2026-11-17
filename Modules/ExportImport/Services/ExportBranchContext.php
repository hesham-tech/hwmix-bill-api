<?php

namespace Modules\ExportImport\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExportBranchContext
{
    /** Capture one authorized branch now; the worker must not read a live request context. */
    public static function resolve(User $user, Request $request): array
    {
        $company = (int) $user->active_company_id;
        if ($company <= 0) throw new AuthorizationException('اختر شركة مصرحًا لك بها.');
        setPermissionsTeamId($company);
        $user->unsetRelation('permissions')->unsetRelation('roles');
        $super = $user->checkPermissionTo(perm_key('admin.super'));
        if (!$super && !DB::table('company_user')->where('company_id', $company)
            ->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw new AuthorizationException('لا تملك عضوية فعالة في الشركة.');
        }
        $manager = $super || $user->checkPermissionTo(perm_key('admin.company'));
        $allowed = $manager ? [] : array_map('intval', $user->getAllowedBranchIds());
        $branch = $request->hasHeader('X-Branch-Id') ? $request->header('X-Branch-Id')
            : (config('app.active_branch_id') ?? $user->branch_id ?? ($allowed[0] ?? null));
        if (!is_scalar($branch) || !ctype_digit((string) $branch) || (int) $branch <= 0) {
            throw ValidationException::withMessages(['branch_id' => ['حدد فرعًا واحدًا لتصدير منتجاته. وضع كل الفروع مخصص للعرض.']]);
        }
        $branch = (int) $branch;
        if ((!$manager && !in_array($branch, $allowed, true)) || !DB::table('branches')
            ->where('id', $branch)->where('company_id', $company)->whereNull('deleted_at')->exists()) {
            throw new AuthorizationException('غير مصرح بالتصدير من هذا الفرع.');
        }
        return ['company_id' => $company, 'branch_id' => $branch];
    }
}
