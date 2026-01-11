<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ScopePermissionsByCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $activeCompanyId = $user->company_id;

            // Set the Spatie Permissions Team ID to the current active company
            // This scopes all can(), hasRole(), etc. to this company
            if ($activeCompanyId) {
                setPermissionsTeamId($activeCompanyId);
            }
        }

        return $next($request);
    }
}
