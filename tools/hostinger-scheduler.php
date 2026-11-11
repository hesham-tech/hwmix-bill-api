<?php
/** Copy to /home/u715355537/.hwnix-ops/scheduler.php (mode 600); Cron invokes PHP once a minute. */
declare(strict_types=1);
umask(0077);
$directory='/home/u715355537/.hwnix-ops';
$root='/home/u715355537/domains/hwnix.com/public_html/bill-api';
$log=$directory.'/scheduler.log';
$lock=fopen($directory.'/scheduler.lock','c');
if($lock===false){fwrite(STDERR,"Scheduler lock unavailable\n");exit(1);}
if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);exit(0);}
try {
    if(!chdir($root))throw new RuntimeException('Project directory unavailable');
    clearstatcache(true,$log);
    if(is_file($log)&&filesize($log)>=5*1024*1024){
        if(!rename($log,$log.'.1'))throw new RuntimeException('Log rotation unavailable');
        chmod($log.'.1',0600);
    }
    file_put_contents($log,'['.gmdate('c')."] scheduler wrapper started\n",FILE_APPEND|LOCK_EX);
    chmod($log,0600);
    $process=proc_open(['/usr/bin/php',$root.'/artisan','schedule:run','--no-interaction'],
        [0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Scheduler process unavailable');
    $status=proc_close($process);
    file_put_contents($log,'['.gmdate('c').'] scheduler wrapper exit='.$status."\n",FILE_APPEND|LOCK_EX);
}catch(Throwable $error){
    file_put_contents($log,'['.gmdate('c')."] scheduler wrapper failed; inspect hosting process permissions\n",FILE_APPEND|LOCK_EX);
    $status=1;
}finally{flock($lock,LOCK_UN);fclose($lock);}
exit($status);
