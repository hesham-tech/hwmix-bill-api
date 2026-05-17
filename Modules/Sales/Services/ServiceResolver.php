<?php

namespace Modules\Sales\Services;

use App\Exceptions\InvalidInvoiceTypeCodeException;
use Modules\Core\Services\DocumentServiceInterface;

class ServiceResolver
{
    public static function resolve(string $invoiceTypeCode): DocumentServiceInterface
    {
        return match ($invoiceTypeCode) {
            'sale' => app(SaleInvoiceService::class),
            'purchase' => app(PurchaseInvoiceService::class),
            'installment_sale' => app(InstallmentSaleInvoiceService::class),
            'service_invoice' => app(ServiceInvoiceService::class),
            'discount_invoice' => app(DiscountInvoiceService::class),
            'sale_return' => app(ReturnService::class),
            'purchase_return' => app(ReturnService::class),
            'quotation' => app(OrderAndQuotationService::class),
            'sales_order' => app(OrderAndQuotationService::class),
            'purchase_order' => app(OrderAndQuotationService::class),
            'inventory_adjustment' => app(\Modules\Inventory\Services\InventoryService::class),
            'stock_transfer' => app(\Modules\Inventory\Services\InventoryService::class),
            'receipt' => app(\Modules\Accounting\Services\FinancialTransactionService::class),
            'payment' => app(\Modules\Accounting\Services\FinancialTransactionService::class),
            'credit_note' => app(\Modules\Accounting\Services\FinancialTransactionService::class),
            'debit_note' => app(\Modules\Accounting\Services\FinancialTransactionService::class),
            default => throw new InvalidInvoiceTypeCodeException('Invalid invoice type code: ' . $invoiceTypeCode),
        };
    }
}
