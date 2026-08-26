<?php

namespace App\Services;

use App\Models\Revenue;
use App\Contracts\FinancialEngineInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class RevenueService
{
    protected FinancialEngineInterface $engine;

    public function __construct(FinancialEngineInterface $engine)
    {
        $this->engine = $engine;
    }

    public function createRevenue(array $data): Revenue
    {
        return DB::transaction(function () use ($data) {
            $operationId = (string) Str::uuid();

            $revenue = Revenue::create([
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? 0,
                'user_id' => $data['user_id'] ?? null,
                'created_by' => $data['created_by'],
                'wallet_id' => $data['wallet_id'],
                'company_id' => $data['company_id'],
                'amount' => $data['amount'],
                'paid_amount' => $data['amount'],
                'remaining_amount' => 0,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'note' => $data['note'] ?? null,
                'revenue_date' => $data['revenue_date'] ?? now(),
                'status' => 'completed',
                'financial_operation_id' => $operationId,
            ]);

            $this->engine->processRevenueCreation($revenue, [
                'operation_id' => $operationId,
                'note' => $revenue->note,
            ]);

            return $revenue;
        });
    }

    public function reverseRevenue(Revenue $revenue, int $userId): void
    {
        if ($revenue->status === 'reversed') {
            throw new Exception('Ã™â€¡Ã˜Â°Ã˜Â§ Ã˜Â§Ã™â€žÃ˜Â¥Ã™Å Ã˜Â±Ã˜Â§Ã˜Â¯ Ã˜ÂªÃ™â€¦ Ã˜Â¹Ã™Æ’Ã˜Â³Ã™â€¡ Ã™â€¦Ã˜Â³Ã˜Â¨Ã™â€šÃ˜Â§Ã™â€¹.');
        }

        DB::transaction(function () use ($revenue, $userId) {
            if ($revenue->financial_operation_id) {
                $this->engine->reverseOperation($revenue->financial_operation_id, 'Ã˜Â¹Ã™Æ’Ã˜Â³ Ã˜Â¥Ã™Å Ã˜Â±Ã˜Â§Ã˜Â¯ Ã˜Â¹Ã˜Â¨Ã˜Â± Ã˜Â§Ã™â€žÃ™â€ Ã˜Â¸Ã˜Â§Ã™â€¦');
            }

            $revenue->status = 'reversed';
            
            $revenue->save();
            
        });
    }
}


