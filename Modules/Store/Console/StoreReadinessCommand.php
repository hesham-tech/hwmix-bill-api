<?php
namespace Modules\Store\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};
use Modules\Store\Services\{StoreReadinessService,StoreSettingsService};
class StoreReadinessCommand extends Command {
    protected $signature='store:readiness {--launch : Require production launch configuration}';
    protected $description='Read-only configuration audit; never sends messages or runs store tasks.';
    public function handle(): int {
        $issues=[];
        try {
            if(!Schema::hasTable('store_settings'))$issues[]='ترحيلات المتجر لم تكتمل.';
            else {
                $settings=app(StoreSettingsService::class)->get();$issues=app(StoreReadinessService::class)->issues($settings);
                if($this->option('launch')) {
                    if(!app()->environment('production'))$issues[]='البيئة المفحوصة ليست production.';
                    if(config('app.debug'))$issues[]='يجب تعطيل APP_DEBUG.';
                    if(!$settings['enabled'])$issues[]='المتجر لم يُفتح لاستقبال الطلبات.';
                    if(!$settings['cod_enabled']&&!$settings['online_enabled'])$issues[]='يلزم تفعيل طريقة دفع واحدة على الأقل؛ لا يشترط تفعيل بوابة مؤجلة.';
                }
                $applied=DB::table('migrations')->pluck('migration')->all();
                foreach(glob(database_path('migrations/*.php')) as $file)
                    if(!in_array(pathinfo($file,PATHINFO_FILENAME),$applied,true)){$issues[]='يوجد ترحيل في النسخة الحالية لم يُطبق.';break;}
            }
        } catch(\Throwable $error){$issues[]='تعذر استكمال الفحص: '.class_basename($error);}
        $this->line(json_encode(['scope'=>'configuration_only','environment'=>app()->environment(),'ready'=>!$issues,
            'provider_sandbox_live_validation'=>'required_separately','issues'=>array_values(array_unique($issues))],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return $issues?self::FAILURE:self::SUCCESS;
    }
}
