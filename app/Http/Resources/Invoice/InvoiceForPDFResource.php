<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceForPDFResource extends JsonResource
{
    /**
     * Transform the resource into an array for PDF generation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            // Basic Invoice Info
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => [
                'id' => $this->invoiceType->id ?? null,
                'name' => $this->invoiceType->name ?? null,
                'code' => $this->invoiceType->code ?? null,
            ],
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            // Dates
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'due_date' => $this->due_date ? \Carbon\Carbon::parse($this->due_date)->format('Y-m-d') : null,
            'formatted_date' => $this->created_at?->format('d/m/Y'),
            'formatted_due_date' => $this->due_date ? \Carbon\Carbon::parse($this->due_date)->format('d/m/Y') : null,

            // Company Info
            'company' => [
                'id' => $this->company->id ?? null,
                'name' => $this->company->name ?? 'اسم الشركة',
                'address' => $this->company->address ?? null,
                'phone' => $this->company->phone ?? null,
                'email' => $this->company->email ?? null,
                'tax_number' => $this->company->tax_number ?? null,
                'logo_url' => $this->company->logo_url ?? null,
            ],

            // Customer Info
            'customer' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email ?? null,
                'phone' => $this->user->phone ?? null,
                'address' => $this->user->address ?? null,
            ] : null,

            // Items
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description ?? null,
                    'sku' => $item->variant->sku ?? null,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount' => (float) ($item->discount ?? 0),
                    'tax_rate' => (float) ($item->tax_rate ?? 0),
                    'tax_amount' => (float) ($item->tax_amount ?? 0),
                    'subtotal' => (float) ($item->subtotal ?? 0),
                    'total' => (float) $item->total,

                    // Formatted for display
                    'formatted_quantity' => number_format($item->quantity, 2),
                    'formatted_unit_price' => number_format($item->unit_price, 2),
                    'formatted_discount' => number_format($item->discount ?? 0, 2),
                    'formatted_tax_amount' => number_format($item->tax_amount ?? 0, 2),
                    'formatted_total' => number_format($item->total, 2),
                ];
            }),

            // Totals
            'totals' => [
                'gross_amount' => (float) $this->gross_amount,
                'total_discount' => (float) $this->total_discount,
                'total_tax' => (float) $this->total_tax,
                'net_amount' => (float) $this->net_amount,
                'paid_amount' => (float) $this->paid_amount,
                'remaining_amount' => (float) $this->remaining_amount,

                // Formatted
                'formatted_gross_amount' => number_format($this->gross_amount, 2),
                'formatted_total_discount' => number_format($this->total_discount, 2),
                'formatted_total_tax' => number_format($this->total_tax, 2),
                'formatted_net_amount' => number_format($this->net_amount, 2),
                'formatted_paid_amount' => number_format($this->paid_amount, 2),
                'formatted_remaining_amount' => number_format($this->remaining_amount, 2),
            ],

            // Tax Info
            'tax_info' => [
                'tax_rate' => (float) ($this->tax_rate ?? 0),
                'tax_inclusive' => (bool) ($this->tax_inclusive ?? false),
                'total_tax' => (float) $this->total_tax,
            ],

            // Notes
            'notes' => $this->notes,

            // Payments (if loaded)
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_date' => $payment->payment_date,
                        'payment_method' => $payment->paymentMethod->name ?? null,
                        'notes' => $payment->notes,
                        'formatted_amount' => number_format($payment->amount, 2),
                    ];
                });
            }),

            // Metadata for PDF generation
            'pdf_metadata' => [
                'direction' => 'rtl',
                'language' => 'ar',
                'currency' => 'ج.م',
                'date_format' => 'd/m/Y',
                'number_format' => [
                    'decimals' => 2,
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                ],
            ],
        ];
    }
}
