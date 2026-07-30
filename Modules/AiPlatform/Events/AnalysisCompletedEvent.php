<?php
// حدث إتمام عملية التحليل المنصي بنجاح بالمنظومة.

namespace Modules\AiPlatform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\AiPlatform\DTOs\AnalysisResultDTO;

class AnalysisCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AnalysisResultDTO $result
    ) {}
}
