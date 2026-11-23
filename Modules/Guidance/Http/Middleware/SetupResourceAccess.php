<?php
namespace Modules\Guidance\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Modules\Guidance\Services\CompanySetupService;
class SetupResourceAccess {
    public function handle(Request $request,Closure $next,string $permission){
        $u=$request->user();$company=app(CompanySetupService::class)->company($u);
        $root=$u->checkPermissionTo('admin.super');$admin=$root||$u->checkPermissionTo('admin.company');
        $read=$request->isMethod('get');$action=$read?'view':($request->isMethod('post')?'create':($request->isMethod('delete')?'delete':'update'));
        $names=$action==='create'?[$permission.'.create']:[$permission.'.'.$action.'_all',$permission.'.'.$action.'_children',$permission.'.'.$action.'_self'];
        abort_unless($admin||collect($names)->contains(fn($p)=>$u->checkPermissionTo($p)),403,'هذا الإعداد يحتاج صلاحية من مسؤول الشركة.');
        $models=[
            'mail_settings'=>\Modules\Notification\Models\MailSetting::class,'whatsapp_settings'=>\Modules\Notification\Models\WhatsAppSetting::class,
            'notification_templates'=>\Modules\Notification\Models\NotificationTemplate::class,'notification_workflows'=>\Modules\Notification\Models\NotificationWorkflow::class,
            'brands'=>\Modules\Inventory\Models\Brand::class,'categories'=>\Modules\Inventory\Models\Category::class,
            'warehouses'=>\Modules\Inventory\Models\Warehouse::class,'branches'=>\Modules\Companies\Models\Branch::class,
            'payment_methods'=>\App\Models\PaymentMethod::class,'cash_boxes'=>\App\Models\CashBox::class,
            'users'=>\App\Models\User::class,'roles'=>\Spatie\Permission\Models\Role::class,'invoice_types'=>\App\Models\InvoiceType::class,
        ];
        if($permission==='units')$models['units']=match($request->segment(6)){'unit-groups'=>\Modules\Inventory\Models\UnitGroup::class,'unit-conversions'=>\Modules\Inventory\Models\UnitConversion::class,default=>\Modules\Inventory\Models\Unit::class};
        foreach($request->route()->parameters() as $value){
            $record=$value instanceof Model?$value:((is_numeric($value)&&isset($models[$permission]))?$models[$permission]::withoutGlobalScopes()->find($value):null);
            if(!$record)continue;
            if($record instanceof \App\Models\User){abort_unless($record->companies()->where('companies.id',$company->id)->wherePivot('status','active')->exists(),403);continue;}
            $owner=$record->getAttribute('company_id');
            if($owner!==null)abort_unless((int)$owner===$company->id,403);
            elseif(!$read&&!$root)abort(403,'الإعداد العام للقراءة فقط.');
        }
        if(!$read){
            abort_if($request->filled('company_id')&&(int)$request->input('company_id')!==$company->id,403);
            // Scoped route binding and the existing domain actions remain in force.
            foreach($request->route()->parameters() as $record){
                if(!$record instanceof Model)continue;
                if($record instanceof \App\Models\User){abort_unless($record->companies()->where('companies.id',$company->id)->wherePivot('status','active')->exists(),403);}
                elseif($record->getAttribute('company_id')!==null)abort_unless((int)$record->company_id===$company->id,403);
                elseif(!$root)abort(403,'الإعداد العام للقراءة فقط.');
            }
        }
        return $next($request);
    }
}
