<?php

namespace Modules\AiPlatform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AiPlatform\Contracts\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\Models\AiExecutionRequest;

// وظيفة مجدولة لتنفيذ طلبات الذكاء الاصطناعي في الخلفية
class ExecuteAiRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $ulid)
    {
    }

    public function handle(ExecutionEngineInterface $engine): void
    {
        $record = AiExecutionRequest::where('ulid', $this->ulid)->first();
        
        if (!$record || $record->status === 'cancelled') {
            return;
        }
        
        $request = ExecutionRequestDTO::fromArray($record->payload ?? []);
        $request->ulid = $this->ulid;
        $request->companyId = $record->company_id;
        
        $engine->run($request);
    }
}
