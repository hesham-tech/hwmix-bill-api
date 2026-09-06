<?php

namespace App\Actions\User;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class SyncUserCompaniesAction
{
    /**
     * كلاس مسؤول عن تحديث صلاحيات المستخدم للشركات بشكل صريح مع أرشفة الأحداث.
     * 
     * @param User $targetUser
     * @param array $requestedCompanies
     * @return array
     */
    public function execute(User $targetUser, array $requestedCompanies): array
    {
        $currentCompanies = $targetUser->companies()->pluck('companies.id')->toArray();

        // Convert to integer arrays for safe comparison
        $requestedCompanies = array_map('intval', $requestedCompanies);
        $currentCompanies = array_map('intval', $currentCompanies);

        $toAttach = array_diff($requestedCompanies, $currentCompanies);
        $toDetach = array_diff($currentCompanies, $requestedCompanies);

        $authUser = Auth::user();

        // Perform explicit attach with logging
        foreach ($toAttach as $companyId) {
            $targetUser->companies()->attach($companyId, [
                'created_by' => $authUser ? $authUser->id : null,
                'status' => 'active'
            ]);

            $this->logActivity('منح', 'تمت إضافة الصلاحية', $targetUser, $companyId, $authUser);
        }

        // Perform explicit detach with logging
        foreach ($toDetach as $companyId) {
            $targetUser->companies()->detach($companyId);

            $this->logActivity('حذف', 'تم سحب الصلاحية', $targetUser, $companyId, $authUser);
        }

        return [
            'attached' => array_values($toAttach),
            'detached' => array_values($toDetach),
        ];
    }

    /**
     * Log the explicit action in activity_logs
     */
    private function logActivity(string $actionType, string $actionName, User $targetUser, int $companyId, ?User $authUser): void
    {
        ActivityLog::create([
            'action' => $actionType,
            'model' => 'App\Models\CompanyUser',
            'row_id' => $targetUser->id,
            'description' => "{$actionName} للمستخدم ({$targetUser->name}) للشركة ID: {$companyId}",
            'company_id' => $companyId,
            'user_id' => $authUser ? $authUser->id : null,
            'created_by' => $authUser ? $authUser->id : null,
            'old_values' => json_encode(['user_id' => $targetUser->id, 'company_id' => $companyId]),
            'new_values' => json_encode(['user_id' => $targetUser->id, 'company_id' => $companyId]),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
