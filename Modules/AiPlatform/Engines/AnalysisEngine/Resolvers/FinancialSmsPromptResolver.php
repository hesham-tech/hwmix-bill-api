<?php
// موجه البرومبت المخصص لتحليل الرسائل المالية والقصيرة بالمنصة.

namespace Modules\AiPlatform\Engines\AnalysisEngine\Resolvers;

use Modules\AiPlatform\Contracts\Resolvers\PromptResolverInterface;

class FinancialSmsPromptResolver implements PromptResolverInterface
{
    public function resolve(string $content, string $providerKey = 'general'): array
    {
        $promptVersion = '1.0';
        $schemaVersion = '1.0';

        $promptText = <<<PROMPT
You are an information extraction engine for financial mobile wallet SMS notifications.

Analyze the following SMS and return ONLY a valid JSON object.
Do not return Markdown, code blocks, explanations, comments, or extra text.

Rules:
- Extract only information explicitly present in the message.
- Never guess, infer, or convert words to numbers.
- Any missing or invalid value must be null.
- Explicitly extract the name of the sender, receiver, merchant, or target person into "transaction.name" if found in the message (e.g. "تم تحويل لفلان", "استلمت من فلان").
- Explicitly extract the service fee / commission / transfer charge into "transaction.fee" as a number if mentioned in the message under any Arabic synonym (e.g. "مصاريف الخدمة 1.00", "مصاريف: 1", "الرسوم 1 جنيه", "بمصاريف 1", "عمولة 1", "خصم 1ج مصاريف"), or calculate total_deducted - transfer_amount if explicitly broken down. Otherwise set to 0.0 or null.
- If the message is not a financial transaction (promotion, advertisement, instruction, notification, etc.), set "is_transaction" to false.
- If the message contains the current wallet/account balance, set "balance.found" to true and extract the numeric value into "balance.available".
- Otherwise set "balance.found" to false and "balance.available" to null.

Allowed values for transaction.type:
receive
send
withdraw
deposit
payment
refund
transfer
balance_inquiry
unknown

Return this exact JSON schema:

{
  "schema_version": "{$schemaVersion}",
  "is_transaction": false,
  "transaction": {
    "type": "unknown",
    "amount": null,
    "fee": null,
    "currency": null,
    "phone": null,
    "name": null,
    "transaction_id": null,
    "datetime": null
  },
  "balance": {
    "found": false,
    "available": null
  }
}

SMS:
{$content}
PROMPT;

        return [
            'prompt_text' => $promptText,
            'prompt_version' => $promptVersion,
            'schema_version' => $schemaVersion,
        ];
    }
}
