<?php

namespace App\Services;

use App\Models\FinancialOperation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * خدمة إدارة دورة حياة العمليات المالية من إنشاء وتحقق وعكس.
 */
class FinancialOperationService
{
    public function createOperation(array $data): FinancialOperation
    {
        return FinancialOperation::create([
            'id' => $data['id'] ?? (string) Str::uuid(),
            'company_id' => $data['company_id'],
            'type' => $data['type'],
            'status' => $data['status'] ?? 'active',
            'amount' => $data['amount'],
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'created_by' => Auth::id() ?? $data['created_by'] ?? null,
        ]);
    }

    public function getOperation(string $id): ?FinancialOperation
    {
        return FinancialOperation::find($id);
    }

    public function markAsReversed(string $id): void
    {
        $op = FinancialOperation::findOrFail($id);
        if ($op->status === 'reversed') {
            throw new \Exception("العملية المالية ملغاة مسبقاً ولا يمكن إلغاؤها مجدداً.");
        }
        $op->status = 'reversed';
        $op->save();
    }
}
