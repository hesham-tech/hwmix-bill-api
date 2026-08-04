<?php
// محول استجابة بيانات معاملات المحافظ الإلكترونية للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $canViewParsedBy = $user && (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('admin.company')) ||
            $user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.view_parsed_by'))
        );

        $canViewParserStage = $user && (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('admin.company')) ||
            $user->hasPermissionTo(perm_key('hwnix_cash_wallet_transactions.view_parser_stage'))
        );

        return [
            'id' => $this->id,
            'financial_account_id' => $this->financial_account_id,
            'line_id' => $this->financialAccount?->line_id,
            'operation_type' => $this->operation_type,
            'transaction_type' => $this->operation_type,
            'provider' => $this->provider,
            'status' => $this->status,
            'source' => $this->source,
            'parsed_by' => $this->when($canViewParsedBy, $this->parsed_by ?? $this->metadata['parsed_by'] ?? $this->metadata['normalized_dto']['executionMetadata']['ai_model'] ?? null),
            'parser_stage' => $this->when($canViewParserStage, $this->parser_stage ?? $this->metadata['parser_stage'] ?? (isset($this->metadata['normalized_dto']) ? 'ai' : null)),
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'balance_after' => $this->balance_after !== null ? (float) $this->balance_after : null,
            'currency' => $this->currency ?? 'EGP',
            'operation_number' => $this->operation_number,
            'reference_number' => $this->operation_number,
            'operation_at' => $this->operation_at?->toIso8601String(),
            'transaction_date' => $this->operation_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'target_phone' => $this->target_phone,
            'target_name' => $this->target_name,
            'bill_number' => $this->bill_number,
            'raw_sms' => $this->raw_sms,
            'metadata' => $this->metadata,
            'financial_account' => $this->financialAccount ? [
                'id' => $this->financialAccount->id,
                'name' => $this->financialAccount->name,
                'sender_identifier' => $this->financialAccount->messageSource?->sender_identifier,
                'account_number' => $this->financialAccount->account_number,
            ] : null,
            'line' => $this->financialAccount?->line ? [
                'id' => $this->financialAccount->line->id,
                'phone_number' => $this->financialAccount->line->phone_number,
                'carrier' => $this->financialAccount->line->carrier,
                'provider' => $this->financialAccount->line->carrier,
                'device_name' => $this->financialAccount->line->device?->device_name ?? 'غير محدد',
                'device_brand' => $this->financialAccount->line->device?->brand,
                'device_model' => $this->financialAccount->line->device?->model,
                'slot_index' => $this->financialAccount->line->slot_index,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
