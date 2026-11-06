<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Modules\Notification\Services\ErpScheduledWorkflowDispatcher;
class ProcessScheduledWorkflows extends Command {
 protected $signature='notifications:process-workflows {--limit=100} {--seconds=20}';
 protected $description='Queue bounded, deduplicated invoice reminders; recover missed business dates.';
 public function handle(): int {
  $count=app(ErpScheduledWorkflowDispatcher::class)->scan((int)$this->option('limit'),(int)$this->option('seconds'));
  $this->info("Invoice reminder scan: {$count}.");
  return self::SUCCESS;
 }
}
