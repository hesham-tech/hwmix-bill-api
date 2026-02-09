<?php

namespace App\Http\Resources\InstallmentPlan;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanBasicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'down_payment' => number_format($this->down_payment ?? 0, 2, '.', ''),
            'total_pay' => number_format($this->total_collected, 2, '.', ''),
            'remaining_amount' => number_format($this->actual_remaining, 2, '.', ''),
            'payment_progress' => $this->payment_progress,

            'interest_rate' => $this->interest_rate,
            'interest_amount' => number_format($this->interest_amount ?? $this->calculated_interest_amount, 2, '.', ''),

            'installment_count' => $this->number_of_installments,
            'installment_amount' => number_format($this->installment_amount ?? 0, 2, '.', ''),
            'number_of_installments' => $this->number_of_installments,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'due_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status ?? 'pending',
            'round_step' => $this->round_step ?? '10',
            'status_label' => $this->getStatusLabel(),
            // ممكن تضيفه لو بتعرض المستخدم في القائمة
            'user_id' => $this->user_id ?? null,
            'invoice' => new \App\Http\Resources\Invoice\InvoiceResource($this->whenLoaded('invoice')),
        ];
    }

    /**
     * ترجمة أو توصيف حالة الخطة.
     */
    protected function getStatusLabel()
    {
        return match ($this->status) {
            'pending' => 'في الانتظار',
            'active' => 'نشطة',
            'paid' => 'مدفوعة ',
            'تم الدفع' => 'مدفوعة ',
            'canceled' => 'ملغاة',
            default => 'غير معروفة',
        };
    }
}
