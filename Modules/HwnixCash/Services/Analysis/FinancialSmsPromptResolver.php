<?php
// كلاس استخراج وديناميكية البرومبت المخصص لكل مصدر رسائل مالية واصداره.

namespace Modules\HwnixCash\Services\Analysis;

class FinancialSmsPromptResolver
{
    /**
     * إرجاع البرومبت المناسب واصداره بناء على نوع مصدر الرسالة القصيرة.
     */
    public function resolvePrompt(string $smsBody, string $providerKey = 'general'): array
    {
        $version = '1.0';
        $schemaVersion = '1.0';

        $promptText = <<<PROMPT
You are an information extraction engine for financial mobile wallet SMS notifications.

Analyze the following SMS and return ONLY a valid JSON object.
Do not return Markdown, code blocks, explanations, comments, or extra text.

Rules:
- Extract only information explicitly present in the message.
- Never guess, infer, or convert words to numbers.
- Any missing or invalid value must be null.
- Always return every required field.
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
{$smsBody}
PROMPT;

        return [
            'prompt_text' => $promptText,
            'prompt_version' => $version,
            'schema_version' => $schemaVersion,
        ];
    }
}
