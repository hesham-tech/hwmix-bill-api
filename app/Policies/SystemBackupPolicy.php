<?php
namespace App\Policies;

use App\Models\User;

final class SystemBackupPolicy
{
    // These archives cover the system, not the active company.
    public function manage(User $user): bool
    {
        return $user->checkPermissionTo(perm_key('admin.super'));
    }
}
