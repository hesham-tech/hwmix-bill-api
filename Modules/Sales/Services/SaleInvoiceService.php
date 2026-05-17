<?php

namespace Modules\Sales\Services;

use App\Models\User;
use Modules\Sales\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use Modules\Accounting\Services\AccountingService;
use Illuminate\Support\Facades\Log;
use App\Services\InvoiceCalculator;
use App\Models\DigitalProductDelivery;

class SaleInvoiceService implements DocumentServiceInterface
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
            Log::info('SaleInvoiceService: بدء إنشاء فاتورة بيع.', ['data' => $data]);

            $calculator = app(InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);
            $data = array_merge($data, $calculatedData);

            if (empty($data['due_date']) && !empty($data['installment_plan'])) {
                $plan = $data['installment_plan'];
                $startDate = isset($plan['start_date']) ? \Carbon\Carbon::parse($plan['start_date']) : now();
                $count = $plan['number_of_installments'] ?? 1;
                $frequency = $plan['frequency'] ?? 'monthly';
                $lastInstallmentDate = $startDate->copy();
                $intervalsToAdd = max(0, $count - 1);

                if ($frequency === 'weekly') {
                    $lastInstallmentDate->addWeeks($intervalsToAdd);
                } elseif ($frequency === 'biweekly') {
                    $lastInstallmentDate->addWeeks($intervalsToAdd * 2);
                } elseif ($frequency === 'quarterly') {
                    $lastInstallmentDate->addMonths($intervalsToAdd * 3);
                } else {
                    $lastInstallmentDate->addMonths($intervalsToAdd);
                }
                $data['due_date'] = $lastInstallmentDate->addMonth()->format('Y-m-d');
            }

            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);
            $invoice = $this->createInvoice($data);
            
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            $invoice->load('items.product');
            foreach ($invoice->items as $item) {
                if ($item->product && $item->product->isDigital()) {
                    try {
                        $delivery = DigitalProductDelivery::create([
                            'invoice_item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'user_id' => $invoice->user_id,
                            'delivery_type' => DigitalProductDelivery::DELIVERY_LICENSE_KEY,
                            'company_id' => $invoice->company_id,
                            'created_by' => $data['created_by'] ?? null,
                        ]);
                        $delivery->deliver();
                    } catch (\Exception $e) {
                        Log::error("فشل تسليم منتج رقمي", ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إنشاء فاتورة البيع.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء تحديث فاتورة بيع.', ['invoice_id' => $invoice->id]);

            $calculator = app(InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);
            $data = array_merge($data, $calculatedData);

            $freshInvoice = Invoice::find($invoice->id);
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            $this->returnStockForItems($invoice);
            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            $this->updateInvoice($invoice, $data);
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'user_cash_box_id' => $data['user_cash_box_id'] ?? null
            ]);

            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في تحديث فاتورة البيع.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء إلغاء فاتورة بيع.', ['invoice_id' => $invoice->id]);
            $this->returnStockForItems($invoice);
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إلغاء فاتورة البيع.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
