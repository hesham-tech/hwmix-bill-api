<?php
// إجراء جدولة وبث الرسائل الصادرة عبر سائق نقل كاش هونكس.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\DTOs\OutgoingSmsData;
use Modules\HwnixCash\Drivers\HwnixCashDriverManager;

class DispatchOutgoingSmsAction
{
    public function __construct(
        protected HwnixCashMessageRepositoryInterface $messageRepo,
        protected HwnixCashDriverManager $driverManager
    ) {}

    public function execute(OutgoingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        $messageEntity = $this->messageRepo->createOutgoing($dto, $companyId, $userId);

        $driver = $this->driverManager->driver();
        $driver->send($messageEntity);

        return $messageEntity;
    }
}
