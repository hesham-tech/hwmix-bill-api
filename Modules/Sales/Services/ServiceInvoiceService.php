<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Services\AccountingService;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;
use Illuminate\Support\Facades\Log;

class ServiceInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    public function create(array $data): Invoice
    {
        try {
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) throw new \Exception('فشل في إنشاء الفاتورة.');

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إنشاء فاتورة الخدمة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            $freshInvoice = Invoice::find($invoice->id);
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            $this->updateInvoice($invoice, $data);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في تحديث فاتورة الخدمة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إلغاء فاتورة الخدمة.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
