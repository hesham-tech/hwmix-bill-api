<?php
namespace App\Console\Commands;
use App\Models\{BackupSetting,Backup};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan,Cache};
class RunScheduledBackup extends Command {
    protected $signature='app:scheduled-backup';
    protected $description='Run a backup when the administrator-configured business time is due.';
    public function handle(): int {
        $frequency=BackupSetting::getVal('backup_frequency','daily');
        if(in_array($frequency,['disabled','none','manual'],true)) return self::SUCCESS;
        if(!in_array($frequency,['daily','weekly','monthly'],true)) return self::FAILURE;
        $at=(string)BackupSetting::getVal('backup_time','03:00');
        if(!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$at)) return self::FAILURE;
        $now=now(config('store.business_timezone','Africa/Cairo'));
        if($now->format('H:i')<$at||($frequency==='weekly'&&$now->dayOfWeek!==0)||($frequency==='monthly'&&$now->day!==1)) return self::SUCCESS;
        $lock=Cache::lock('scheduled-backup',7200);if(!$lock->get())return self::SUCCESS;
        try {
            if(Backup::where('type','scheduled')->where('status','success')->where('completed_at','>=',$now->copy()->startOfDay()->utc())->exists()) return self::SUCCESS;
            $options=BackupSetting::getVal('backup_include_files',false)?[]:['--only-db'=>true];
            $result=Artisan::call('backup:run',$options);
            Backup::create(['status'=>$result===0?'success':'failed','type'=>'scheduled','completed_at'=>now('UTC')]);
            return $result===0?self::SUCCESS:self::FAILURE;
        } finally { $lock->release(); }
    }
}
