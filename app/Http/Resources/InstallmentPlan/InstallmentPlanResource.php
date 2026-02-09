<?php

namespace App\Http\Resources\InstallmentPlan;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Invoice\InvoiceResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Installment\InstallmentResource;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Http\Resources\InstallmentPayment\InstallmentPaymentResource;

class InstallmentPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $installments = collect($this->whenLoaded('installments'));

        // حسابات مالية دقيقة باستخدام BCMath
        $totalInstallmentsRemaining = $installments->reduce(fn($c, $inst) => bcadd($c, $inst->remaining ?? '0', 2), '0.00');
        $totalInstallmentsAmount = $installments->reduce(fn($c, $inst) => bcadd($c, $inst->amount ?? '0', 2), '0.00');
        $totalInstallmentsPay = bcsub($totalInstallmentsAmount, $totalInstallmentsRemaining, 2);

        $totalPay = bcsub($this->total_amount ?? '0', $this->remaining_amount ?? '0', 2);


        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_id' => $this->invoice_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status ?? 'pending',
            'status_label' => $this->getStatusLabel(),
            'round_step' => $this->round_step ?? '10',

            // المبالغ المحسوبة بدقة من الموديل (وحدة الحقيقة)
            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'down_payment' => number_format($this->down_payment, 2, '.', ''),
            'total_pay' => number_format($this->total_collected, 2, '.', ''),
            'remaining_amount' => number_format($this->actual_remaining, 2, '.', ''),
            'payment_progress' => $this->payment_progress,

            'number_of_installments' => $this->number_of_installments ?? $this->installment_count,
            'installment_count' => $this->installment_count,
            'installment_amount' => number_format($this->installment_amount, 2, '.', ''),

            'interest_rate' => $this->interest_rate,
            'interest_amount' => number_format($this->interest_amount ?? $this->calculated_interest_amount, 2, '.', ''),

            'total_installments_remaining' => number_format($totalInstallmentsRemaining, 2, '.', ''),
            'total_installments_amount' => number_format($totalInstallmentsAmount, 2, '.', ''),
            'total_installments_pay' => number_format($totalInstallmentsPay, 2, '.', ''),

            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d H:i:s') : null,
            'due_day' => $this->due_day,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,

            'customer' => new UserBasicResource($this->whenLoaded('customer')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'invoice_items' => InvoiceItemResource::collection(
                $this->whenLoaded('invoice', function () {
                    return $this->invoice->items;
                })
            ),
            'payments' => InstallmentPaymentResource::collection($this->whenLoaded('payments')),
            'installments' => InstallmentResource::collection(
                $installments->sortBy('due_date')
            ),
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
            'paid' => 'مدفوعة بالكامل',
            'canceled' => 'ملغاة',
            'partially_paid' => 'مدفوعة جزئياً',
            default => 'غير معروفة',
        };
    }
}
