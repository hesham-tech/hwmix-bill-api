<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;

class DiscountInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data): Invoice
    {
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        return $invoice;
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        $this->updateInvoice($invoice, $data);
        $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'canceled']);
        $this->deleteInvoiceItems($invoice);
        return $invoice;
    }
}
