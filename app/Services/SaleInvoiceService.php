<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use App\Services\AccountingService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected AccountingService $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    /**
     * إنشاء فاتورة بيع جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء إنشاء فاتورة بيع.', ['data' => $data]);

            // ✅ استخدام InvoiceCalculator لحساب الإجماليات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);

            // دمج البيانات المحسوبة مع البيانات المدخلة
            $data = array_merge($data, $calculatedData);

            // ✅ تحديد تاريخ الاستحقاق في حالة البيع بالتقسيط
            if (empty($data['due_date']) && !empty($data['installment_plan'])) {
                $plan = $data['installment_plan'];
                $startDate = isset($plan['start_date']) ? \Carbon\Carbon::parse($plan['start_date']) : now();
                $count = $plan['number_of_installments'] ?? 1;
                $frequency = $plan['frequency'] ?? 'monthly';

                // حساب تاريخ آخر قسط
                $lastInstallmentDate = $startDate->copy();

                // بما أن القسط الأول هو تاريخ البداية، نضيف (العدد - 1)
                $intervalsToAdd = max(0, $count - 1);

                if ($frequency === 'weekly') {
                    $lastInstallmentDate->addWeeks($intervalsToAdd);
                } elseif ($frequency === 'biweekly') {
                    $lastInstallmentDate->addWeeks($intervalsToAdd * 2);
                } elseif ($frequency === 'quarterly') {
                    $lastInstallmentDate->addMonths($intervalsToAdd * 3);
                } else {
                    // الافتراضي شهري
                    $lastInstallmentDate->addMonths($intervalsToAdd);
                }

                // إضافة شهر واحد على تاريخ آخر قسط
                $data['due_date'] = $lastInstallmentDate->addMonth()->format('Y-m-d');
            }

            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);

            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ استخدام AccountingService لتسجيل الأثر المالي الموحد
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            // ✅ Auto-deliver digital products
            $invoice->load('items.product');
            foreach ($invoice->items as $item) {
                if ($item->product && $item->product->isDigital()) {
                    try {
                        $delivery = \App\Models\DigitalProductDelivery::create([
                            'invoice_item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'user_id' => $invoice->user_id,
                            'delivery_type' => \App\Models\DigitalProductDelivery::DELIVERY_LICENSE_KEY,
                            'company_id' => $invoice->company_id,
                            'created_by' => $data['created_by'] ?? null,
                        ]);

                        $delivery->deliver();

                        Log::info("تم تسليم منتج رقمي تلقائياً", [
                            'invoice_id' => $invoice->id,
                            'product_id' => $item->product_id,
                            'delivery_id' => $delivery->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error("فشل تسليم منتج رقمي", [
                            'invoice_id' => $invoice->id,
                            'product_id' => $item->product_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }


            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إنشاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة بيع موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء تحديث فاتورة بيع.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // ✅ استخدام InvoiceCalculator لحساب الإجماليات
            $calculator = app(\App\Services\InvoiceCalculator::class);
            $calculatedData = $calculator->calculateTotals($data['items'], $data);

            // دمج البيانات المحسوبة
            $data = array_merge($data, $calculatedData);

            $freshInvoice = Invoice::find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }
            $oldPaidAmount = $freshInvoice->paid_amount;
            $oldRemainingAmount = $freshInvoice->remaining_amount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // ✅ عكس الأثر المالي القديم قبل التحديث
            $this->accounting->reverseInvoice($freshInvoice, [
                'cash_box_id' => $freshInvoice->cash_box_id,
                'user_cash_box_id' => $freshInvoice->user_cash_box_id
            ]);

            $this->returnStockForItems($invoice);

            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            $this->updateInvoice($invoice, $data);

            // ✅ تسجيل الأثر المالي الجديد للفاتورة المحدثة
            $this->accounting->recordInvoiceCreation($invoice, [
                'cash_box_id' => $cashBoxId,
                'user_cash_box_id' => $userCashBoxId
            ]);

            $this->checkVariantsStock($data['items'], 'deduct', $data['warehouse_id'] ?? null);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            $this->deductStockForItems($data['items'], $data['warehouse_id'] ?? null);

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }


            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في تحديث فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة بيع.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء إلغاء فاتورة بيع.', ['invoice_id' => $invoice->id]);
            // if ($invoice->status === 'paid') {
            //     throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            // }

            $this->returnStockForItems($invoice);
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            // if ($invoice->installmentPlan) {
            //     app(InstallmentService::class)->cancelInstallments($invoice);
            // }

            $this->accounting->reverseInvoice($invoice, [
                'cash_box_id' => $invoice->cash_box_id,
                'user_cash_box_id' => $invoice->user_cash_box_id
            ]);


            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إلغاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
