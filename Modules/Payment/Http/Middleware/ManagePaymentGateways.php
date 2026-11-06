<?php
namespace Modules\Payment\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class ManagePaymentGateways {
    public function handle(Request $request,Closure $next) {
        $user=$request->user();
        abort_unless($user && ($user->hasPermissionTo(perm_key('admin.super')) || $user->hasPermissionTo(perm_key('companies.update'))),403);
        return $next($request);
    }
}
