<?php

// أمر أرشفة السجلات القديمة
namespace Modules\AiPlatform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveLogsCommand extends Command
{
    protected $signature   = 'ai:archive-logs {--dry-run : معاينة بدون تنفيذ}';
    protected $description = 'أرشفة سجلات AI Platform القديمة للحفاظ على الأداء';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🗄️  بدء أرشفة سجلات AI Platform...');

        $tables = [
            'ai_usage_logs'       => config('ai-platform.archiving.usage_logs_days', 90),
            'ai_router_logs'      => config('ai-platform.archiving.router_logs_days', 30),
            'ai_audit_logs'       => config('ai-platform.archiving.audit_logs_days', 180),
            'ai_policy_evaluations' => 90,
            'ai_tool_executions'  => 90,
        ];

        foreach ($tables as $table => $days) {
            $cutoff = now()->subDays($days);
            $count  = DB::table($table)->where('created_at', '<', $cutoff)->count();

            if ($count === 0) {
                $this->line("  ✓ {$table}: لا سجلات للأرشفة");
                continue;
            }

            $archiveTable = $table . '_archive_' . now()->format('Y');

            if (!$dryRun) {
                // إنشاء جدول الأرشيف إن لم يكن موجوداً
                DB::statement("CREATE TABLE IF NOT EXISTS {$archiveTable} LIKE {$table}");
                // نقل السجلات
                DB::statement("INSERT INTO {$archiveTable} SELECT * FROM {$table} WHERE created_at < ?", [$cutoff]);
                // حذف من الجدول الأصلي
                DB::table($table)->where('created_at', '<', $cutoff)->delete();

                $this->info("  ✅ {$table}: نُقل {$count} سجل إلى {$archiveTable}");
            } else {
                $this->warn("  [dry-run] {$table}: سيُنقل {$count} سجل إلى {$archiveTable}");
            }
        }

        $this->info('✅ اكتملت الأرشفة.');
        return self::SUCCESS;
    }
}
