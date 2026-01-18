<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class InvoicePaymentHandler
{
    /**
     * معالجة دفع فاتورة بيع
     *
     * @param Invoice $invoice
     * @param User $seller
     * @param User|null $buyer
     * @param float $paidAmount
     * @param float $remainingAmount
     * @param int|null $sellerCashBoxId
     * @param int|null $buyerCashBoxId
     * @return void
     * @throws \Exception
     */
    public function handleSalePayment(
        Invoice $invoice,
        User $seller,
        ?User $buyer,
        float $paidAmount,
        float $remainingAmount,
        ?int $sellerCashBoxId = null,
        ?int $buyerCashBoxId = null
    ): void {
        // المبلغ المدفوع → يذهب لخزنة البائع
        if ($paidAmount > 0) {
            $result = $seller->deposit($paidAmount, $sellerCashBoxId);
            if ($result !== true) {
                throw new \Exception('فشل إيداع المبلغ في خزنة البائع: ' . json_encode($result));
            }
            Log::info("InvoicePaymentHandler: إيداع {$paidAmount} في خزنة البائع");
        }

        // المبلغ المتبقي → دين على العميل (أو رصيد له في حالة دفع زيادة)
        if ($buyer && $remainingAmount != 0) {
            if ($remainingAmount > 0) {
                // دين على العميل
                $result = $buyer->withdraw($remainingAmount, $buyerCashBoxId);
                $logMsg = "InvoicePaymentHandler: خصم دين {$remainingAmount} من رصيد العميل";
            } else {
                // دفع زيادة → تضاف لرصيد العميل
                $overAmount = abs($remainingAmount);
                $result = $buyer->deposit($overAmount, $buyerCashBoxId);
                $logMsg = "InvoicePaymentHandler: إيداع مبلغ متبقي {$overAmount} في رصيد العميل";
            }

            if ($result !== true) {
                throw new \Exception('فشل معالجة رصيد العميل المتبقي: ' . json_encode($result));
            }
            Log::info($logMsg);
        }

        // تحديث حالة الدفع
        $invoice->updatePaymentStatus();
    }

    /**
     * معالجة دفع فاتورة شراء
     *
     * @param Invoice $invoice
     * @param User $buyer
     * @param User|null $supplier
     * @param float $paidAmount
     * @param float $remainingAmount
     * @param int|null $buyerCashBoxId
     * @param int|null $supplierCashBoxId
     * @return void
     * @throws \Exception
     */
    public function handlePurchasePayment(
        Invoice $invoice,
        User $buyer,
        ?User $supplier,
        float $paidAmount,
        float $remainingAmount,
        ?int $buyerCashBoxId = null,
        ?int $supplierCashBoxId = null
    ): void {
        // المبلغ المدفوع → يُسحب من خزنة المشتري (الشركة)
        if ($paidAmount > 0) {
            $result = $buyer->withdraw($paidAmount, $buyerCashBoxId);
            if ($result !== true) {
                throw new \Exception('فشل سحب المبلغ من خزنة الشركة: ' . json_encode($result));
            }
            Log::info("InvoicePaymentHandler: سحب {$paidAmount} من خزنة الشركة");
        }

        // المبلغ المتبقي → دين للمورد على الشركة (أو رصيد للشركة عند دفع زيادة)
        if ($supplier && $remainingAmount != 0) {
            if ($remainingAmount > 0) {
                // دين للمورد على الشركة
                $result = $supplier->deposit($remainingAmount, $supplierCashBoxId);
                $logMsg = "InvoicePaymentHandler: إضافة دين {$remainingAmount} لرصيد المورد";
            } else {
                // دفع زيادة → يُخصم من رصيد المورد (يصبح مديناً للشركة)
                $overAmount = abs($remainingAmount);
                $result = $supplier->withdraw($overAmount, $supplierCashBoxId);
                $logMsg = "InvoicePaymentHandler: خصم {$overAmount} من رصيد المورد (دفع زيادة)";
            }

            if ($result !== true) {
                throw new \Exception('فشل معالجة رصيد المورد المتبقي: ' . json_encode($result));
            }
            Log::info($logMsg);
        }

        $invoice->updatePaymentStatus();
    }

    /**
     * عكس المدفوعات عند الإلغاء
     *
     * @param Invoice $invoice
     * @param string $invoiceTypeCode
     * @param User $currentUser
     * @param int|null $cashBoxId
     * @param int|null $userCashBoxId
     * @return void
     * @throws \Exception
     */
    public function reversePayment(
        Invoice $invoice,
        string $invoiceTypeCode,
        User $currentUser,
        ?int $cashBoxId = null,
        ?int $userCashBoxId = null
    ): void {
        if (in_array($invoiceTypeCode, ['sale', 'installment_sale', 'service_invoice'])) {
            $this->reverseSalePayment($invoice, $currentUser, $cashBoxId, $userCashBoxId);
        } elseif ($invoiceTypeCode === 'purchase') {
            $this->reversePurchasePayment($invoice, $currentUser, $cashBoxId, $userCashBoxId);
        }
    }

    /**
     * عكس دفع فاتورة بيع
     */
    private function reverseSalePayment(
        Invoice $invoice,
        User $seller,
        ?int $sellerCashBoxId,
        ?int $buyerCashBoxId
    ): void {
        // عكس المبلغ المدفوع (سحب من خزنة البائع)
        if ($invoice->paid_amount > 0) {
            $result = $seller->withdraw($invoice->paid_amount, $sellerCashBoxId);
            if ($result !== true) {
                Log::error('InvoicePaymentHandler: فشل عكس المبلغ المدفوع', ['result' => $result]);
            }
        }

        // عكس الدين/الرصيد المتبقي
        if ($invoice->user_id && $invoice->remaining_amount != 0) {
            $buyer = User::find($invoice->user_id);
            if ($buyer) {
                if ($invoice->remaining_amount > 0) {
                    $result = $buyer->deposit($invoice->remaining_amount, $buyerCashBoxId);
                } else {
                    $result = $buyer->withdraw(abs($invoice->remaining_amount), $buyerCashBoxId);
                }

                if ($result !== true) {
                    Log::error('InvoicePaymentHandler: فشل عكس الرصيد المتبقي للعميل', ['result' => $result]);
                }
            }
        }
    }

    /**
     * عكس دفع فاتورة شراء
     */
    private function reversePurchasePayment(
        Invoice $invoice,
        User $buyer,
        ?int $buyerCashBoxId,
        ?int $supplierCashBoxId
    ): void {
        // عكس المبلغ المدفوع (إرجاع لخزنة الشركة)
        if ($invoice->paid_amount > 0) {
            $result = $buyer->deposit($invoice->paid_amount, $buyerCashBoxId);
            if ($result !== true) {
                Log::error('InvoicePaymentHandler: فشل عكس المبلغ المدفوع للشركة', ['result' => $result]);
            }
        }

        // عكس الدين/الرصيد للمورد
        if ($invoice->user_id && $invoice->remaining_amount != 0) {
            $supplier = User::find($invoice->user_id);
            if ($supplier) {
                if ($invoice->remaining_amount > 0) {
                    $result = $supplier->withdraw($invoice->remaining_amount, $supplierCashBoxId);
                } else {
                    $result = $supplier->deposit(abs($invoice->remaining_amount), $supplierCashBoxId);
                }

                if ($result !== true) {
                    Log::error('InvoicePaymentHandler: فشل عكس رصيد المورد المتبقي', ['result' => $result]);
                }
            }
        }
    }
}
