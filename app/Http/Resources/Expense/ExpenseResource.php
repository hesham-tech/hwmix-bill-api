<?php

namespace App\Http\Resources\Expense;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ExpenseCategory\ExpenseCategoryResource;
use App\Http\Resources\User\UserBasicResource;

class ExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'expense_date' => $this->expense_date->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'expense_category' => new ExpenseCategoryResource($this->whenLoaded('category')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
