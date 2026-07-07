<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * خدمة المحاسبة (AccountingService) - موديول المحاسبة
 * تتضمن منطق العزل المالي للعميل النقدي الافتراضي (cash_customer) بحيث لا تُسجَّل
 * أي ذمم أو حركات رصيد باسمه مع الحفاظ الكامل على تدفق الخزينة للمؤسسة.
 */
class AccountingService
{
    /**
     * تسجيل الأثر المالي لإنشاء فاتورة.
     */
    public function recordInvoiceCreation(Invoice $invoice, array $options = []): void
    {
        DB::transaction(function () use ($invoice, $options) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            
            $cashBoxId = $options['cash_box_id'] ?? null;
            $userCashBoxId = $options['user_cash_box_id'] ?? null;
            
            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $authUser = Auth::user();

            // العميل النقدي الافتراضي: لا تسجيل ذمم أو حركات رصيد على حسابه
            $isCashCustomer = $party && $party->isDefaultCashCustomer($invoice->company_id);

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // تسجيل المديونية فقط للعملاء الحقيقيين (ليس العميل النقدي)
                if ($party && !$isCashCustomer) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'receivable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance += $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'deposit',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إثبات مديونية فاتورة {$type} رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }

                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "دفعة من فاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $createdById = $authUser ? $authUser->id : $invoice->created_by;

                    // إذا كان العميل حقيقياً وهناك زيادة مدفوعة عن قيمة الفاتورة الصافية
                    if ($paidAmount > $netAmount && $party && !$isCashCustomer) {
                        $excess = $paidAmount - $netAmount;

                        // 1. قصر مدفوع الفاتورة الحالية على قيمتها الصافية فقط
                        $invoice->paid_amount = $netAmount;
                        $invoice->remaining_amount = 0;
                        $invoice->payment_status = Invoice::PAYMENT_PAID;
                        $invoice->save();

                        // تسجيل دفعة للفاتورة الحالية بقيمتها الصافية
                        InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'cash_box_id' => $cashBoxId,
                            'amount' => $netAmount,
                            'payment_date' => now(),
                            'notes' => "سداد كامل قيمة الفاتورة رقم: {$invoice->invoice_number}",
                            'company_id' => $invoice->company_id,
                            'created_by' => $createdById,
                        ]);

                        // 2. جلب وتوزيع الزيادة على أقدم الفواتير غير المدفوعة للعميل
                        $dueInvoices = Invoice::where('user_id', $party->id)
                            ->where('id', '!=', $invoice->id)
                            ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                            ->where('status', '!=', 'canceled')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();

                        /** @var Invoice $dueInvoice */
                        foreach ($dueInvoices as $dueInvoice) {
                            if ($excess <= 0) break;
                            $dueRemaining = (float)$dueInvoice->remaining_amount;
                            if ($dueRemaining <= 0) continue;

                            $allocated = min($excess, $dueRemaining);

                            InvoicePayment::create([
                                'invoice_id' => $dueInvoice->id,
                                'cash_box_id' => $cashBoxId,
                                'amount' => $allocated,
                                'payment_date' => now(),
                                'notes' => "تسوية دفعة زائدة مستلمة من الفاتورة رقم: {$invoice->invoice_number}",
                                'company_id' => $dueInvoice->company_id,
                                'created_by' => $createdById,
                            ]);

                            $dueInvoice->paid_amount += $allocated;
                            $dueInvoice->remaining_amount = max(0, $dueInvoice->net_amount - $dueInvoice->paid_amount);
                            $dueInvoice->updatePaymentStatus();
                            $dueInvoice->save();

                            $excess -= $allocated;
                        }
                    } else {
                        // دفعة عادية مساوية أو أقل من الصافي
                        InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'cash_box_id' => $cashBoxId,
                            'amount' => $paidAmount,
                            'payment_date' => now(),
                            'notes' => "دفعة من فاتورة رقم: {$invoice->invoice_number}",
                            'company_id' => $invoice->company_id,
                            'created_by' => $createdById,
                        ]);
                    }
                }
            } elseif ($type === 'purchase') {
                if ($party && !$isCashCustomer) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'payable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance += $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'withdraw',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إثبات التزام فاتورة شراء رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }

                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "سداد فاتورة شراء رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id' => $invoice->id,
                    ]);
                }
            } elseif ($type === 'sale_return') {
                if ($party && !$isCashCustomer) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'receivable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance -= $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'withdraw',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء مديونية (مرتجع مبيعات) رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ لمرتجع مبيعات رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id' => $invoice->id,
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                if ($party && !$isCashCustomer) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'payable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance -= $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'deposit',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء التزام (مرتجع مشتريات) رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استلام مبلغ لمرتجع مشتريات رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id' => $invoice->id,
                    ]);
                }
            }
        });
    }

    /**
     * تسجيل حركة مالية (قبض أو صرف).
     */
    public function recordPayment(int $companyId, User $staff, ?User $party, float $amount, string $direction, array $options = []): void
    {
        DB::transaction(function () use ($companyId, $staff, $party, $amount, $direction, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $description = $options['description'] ?? ($direction === 'in' ? 'قبض نقدي' : 'صرف نقدي');
            // skip_party_balance: يُمرَّر من recordInvoiceCreation عند التعامل مع العميل النقدي
            $skipPartyBalance = $options['skip_party_balance'] ?? false;

            // جلب صناديق النقدية بدون فلاتر الفروع لحساب الأرصدة بدقة
            $staffBox = \App\Models\CashBox::withoutGlobalScopes()
                ->where('id', $cashBoxId ?? 0)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $staff->id)
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->where('user_id', $staff->id)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first() ?? \App\Models\CashBox::withoutGlobalScopes()
                ->whereNull('user_id')
                ->where('company_id', $companyId)
                ->where('access_type', 'company_shared')
                ->first();

            // حساب رصيد الموظف قبل وبعد
            $staffBalanceBefore = $staffBox ? (float)$staffBox->balance : 0.00;
            $staffBalanceAfter = $direction === 'in' 
                ? $staffBalanceBefore + $amount 
                : $staffBalanceBefore - $amount;

            // حساب رصيد الأطراف المجمع (العميل/المورد)
            $partyBalanceBefore = 0.00;
            $partyBalanceAfter = 0.00;
            $balanceModel = null;

            if ($party && !$skipPartyBalance) {
                $invoiceId = $options['source_invoice_id'] ?? $options['invoice_id'] ?? null;
                $invoice = $invoiceId ? \Modules\Sales\Models\Invoice::withoutGlobalScopes()->find($invoiceId) : null;
                $relationType = ($invoice && in_array($invoice->invoice_type_code, ['purchase', 'purchase_return'])) ? 'payable' : 'receivable';

                $balanceModel = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'user_id' => $party->id,
                        'relation_type' => $relationType,
                    ],
                    [
                        'created_by' => Auth::id() ?? $staff->id,
                    ]
                );
                $partyBalanceBefore = (float)$balanceModel->balance;
                if ($relationType === 'payable') {
                    $partyBalanceAfter = $direction === 'out'
                        ? $partyBalanceBefore - $amount
                        : $partyBalanceBefore + $amount;
                } else {
                    $partyBalanceAfter = $direction === 'in'
                        ? $partyBalanceBefore - $amount
                        : $partyBalanceBefore + $amount;
                }

                $balanceModel->balance = $partyBalanceAfter;
                $balanceModel->save();
            }

            $logOptions = [
                'employee_balance_before' => $staffBalanceBefore,
                'employee_balance_after' => $staffBalanceAfter,
                'client_balance_before' => $partyBalanceBefore,
                'client_balance_after' => $partyBalanceAfter,
                'source_invoice_id' => $options['source_invoice_id'] ?? $options['invoice_id'] ?? null,
                'source_installment_id' => $options['installment_id'] ?? null,
                'is_transfer' => false,
            ];

            if ($direction === 'in') {
                $staff->deposit($amount, $cashBoxId, $description, true, $logOptions);
                if ($party && !$skipPartyBalance) {
                    $txParty = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $staff->id,
                        'company_id' => $companyId,
                        'branch_id' => $staffBox ? $staffBox->branch_id : null,
                        'type' => 'withdraw',
                        'amount' => $amount,
                        'balance_before' => $partyBalanceBefore,
                        'balance_after' => $partyBalanceAfter,
                        'description' => "دفع مبلغ: {$amount} - {$description}",
                        'source_invoice_id' => $options['source_invoice_id'] ?? $options['invoice_id'] ?? null,
                        'source_installment_id' => $options['installment_id'] ?? null,
                    ]);
                    if ($balanceModel) {
                        $balanceModel->last_transaction_id = $txParty->id;
                        $balanceModel->save();
                    }
                }
            } else {
                $staff->withdraw($amount, $cashBoxId, $description, true, $logOptions);
                if ($party && !$skipPartyBalance) {
                    $txParty = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $staff->id,
                        'company_id' => $companyId,
                        'branch_id' => $staffBox ? $staffBox->branch_id : null,
                        'type' => 'deposit',
                        'amount' => $amount,
                        'balance_before' => $partyBalanceBefore,
                        'balance_after' => $partyBalanceAfter,
                        'description' => "استلام مبلغ: {$amount} - {$description}",
                        'source_invoice_id' => $options['source_invoice_id'] ?? $options['invoice_id'] ?? null,
                        'source_installment_id' => $options['installment_id'] ?? null,
                    ]);
                    if ($balanceModel) {
                        $balanceModel->last_transaction_id = $txParty->id;
                        $balanceModel->save();
                    }
                }
            }
        });
    }

    /**
     * عكس الأثر المالي عند إلغاء فاتورة.
     */
    public function reverseInvoice(Invoice $invoice, array $options = []): void
    {
        DB::transaction(function () use ($invoice, $options) {
            $type = $invoice->invoice_type_code;
            $netAmount = (float)$invoice->net_amount;
            $paidAmount = (float)$invoice->paid_amount;
            
            $cashBoxId = $options['cash_box_id'] ?? null;
            $userCashBoxId = $options['user_cash_box_id'] ?? null;
            
            $party = User::withoutGlobalScopes()->find($invoice->user_id);
            $authUser = Auth::user();

            // العميل النقدي الافتراضي: لا عكس لذمم على حسابه
            $isCashCustomer = $party && $party->isDefaultCashCustomer($invoice->company_id);

            if (in_array($type, ['sale', 'installment_sale', 'service_invoice'])) {
                // عكس المديونية دائماً حتى لو لم يُدفع شيء — لأن المديونية نفسها سُجِّلت عند الإنشاء
                if ($party && !$isCashCustomer && $netAmount > 0) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'receivable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance -= $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'withdraw',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء مديونية فاتورة {$type} رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                } elseif ($isCashCustomer && $netAmount > 0) {
                    // العميل النقدي: لا ذمم على حسابه لكن نسجّل Transaction توثيقية للإلغاء
                    \App\Models\Transaction::create([
                        'user_id'         => $party?->id,
                        'cashbox_id'      => null,
                        'created_by'      => $authUser?->id,
                        'company_id'      => $invoice->company_id,
                        'type'            => 'invoice_cancel',
                        'amount'          => $netAmount,
                        'balance_before'  => 0,
                        'balance_after'   => 0,
                        'source_invoice_id' => $invoice->id,
                        'description'     => "إلغاء فاتورة نقدية {$type} رقم: {$invoice->invoice_number}",
                    ]);
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id'  => $invoice->id,
                    ]);
                }
            } elseif ($type === 'purchase') {
                if ($party && !$isCashCustomer && $netAmount > 0) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'payable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance -= $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'deposit',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء التزام فاتورة شراء رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ مدفوع لإلغاء الفاتورة رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id'  => $invoice->id,
                    ]);
                }
            } elseif ($type === 'sale_return') {
                if ($party && !$isCashCustomer && $netAmount > 0) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'receivable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance += $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'deposit',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء أثر مرتجع مبيعات رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'in', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "استرداد مبلغ رد للعميل (إلغاء مرتجع) رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id'  => $invoice->id,
                    ]);
                }
            } elseif ($type === 'purchase_return') {
                if ($party && !$isCashCustomer && $netAmount > 0) {
                    $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                        [
                            'company_id' => $invoice->company_id,
                            'user_id' => $party->id,
                            'relation_type' => 'payable',
                        ],
                        [
                            'created_by' => Auth::id() ?? $invoice->created_by,
                        ]
                    );
                    $balanceBefore = (float)$balance->balance;
                    $balance->balance += $netAmount;
                    $balance->save();

                    $tx = \App\Models\Transaction::create([
                        'user_id' => $party->id,
                        'cashbox_id' => null,
                        'created_by' => Auth::id() ?? $invoice->created_by,
                        'company_id' => $invoice->company_id,
                        'branch_id' => $invoice->branch_id,
                        'type' => 'withdraw',
                        'amount' => $netAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balance->balance,
                        'description' => "إلغاء أثر مرتجع مشتريات رقم: {$invoice->invoice_number}",
                        'source_invoice_id' => $invoice->id,
                    ]);

                    $balance->last_transaction_id = $tx->id;
                    $balance->save();
                }
                if ($paidAmount > 0) {
                    $this->recordPayment($invoice->company_id, $authUser, $party, $paidAmount, 'out', [
                        'cash_box_id' => $cashBoxId,
                        'party_cash_box_id' => $userCashBoxId,
                        'description' => "رد مبلغ استلم من المورد (إلغاء مرتجع) رقم: {$invoice->invoice_number}",
                        'skip_party_balance' => $isCashCustomer,
                        'source_invoice_id'  => $invoice->id,
                    ]);
                }
            }
        });
    }

    /**
     * تحصيل دفعة من طرف (عميل أو مورد) وتوزيعها على الفواتير المتأخرة.
     */
    public function collectPayment(User $staff, User $party, float $amount, array $options = []): void
    {
        DB::transaction(function () use ($staff, $party, $amount, $options) {
            $cashBoxId = $options['cash_box_id'] ?? null;
            $partyCashBoxId = $options['party_cash_box_id'] ?? null;
            $notes = $options['notes'] ?? 'تحصيل دفعة حساب';
            $paymentDate = $options['payment_date'] ?? now();
            $targetInvoiceId = $options['invoice_id'] ?? null;
            $mode = $options['mode'] ?? 'cash';

            if ($mode === 'cash') {
                $staff->deposit($amount, $cashBoxId, "تحصيل نقدي من {$party->name} - {$notes}");
            }

            $remaining = $amount;
            $query = Invoice::where('user_id', $party->id)
                ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIALLY_PAID])
                ->where('status', '!=', 'canceled')
                ->orderBy('id', 'asc')
                ->lockForUpdate();

            $dueInvoices = $query->get();

            if ($targetInvoiceId) {
                $selected = $dueInvoices->where('id', $targetInvoiceId)->first();
                if ($selected) {
                    $dueInvoices = $dueInvoices->reject(fn($inv) => $inv->id == $targetInvoiceId)->prepend($selected);
                }
            }

            /** @var Invoice $invoice */
            foreach ($dueInvoices as $invoice) {
                if ($remaining <= 0) break;
                $invoiceRemaining = (float)$invoice->remaining_amount;
                if ($invoiceRemaining <= 0) continue;

                $paymentForThisInvoice = min($remaining, $invoiceRemaining);

                // Use the correct model for InvoicePayment (might be in App\Models or moved)
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'cash_box_id' => $cashBoxId,
                    'amount' => $paymentForThisInvoice,
                    'payment_date' => $paymentDate,
                    'notes' => $notes . ($mode === 'balance' ? ' (تسوية من الرصيد)' : ''),
                    'company_id' => $invoice->company_id,
                    'created_by' => $staff->id,
                ]);

                $invoice->paid_amount += $paymentForThisInvoice;
                $invoice->remaining_amount = max(0, $invoice->net_amount - $invoice->paid_amount);
                $invoice->updatePaymentStatus();
                $invoice->save();

                $remaining -= $paymentForThisInvoice;
            }

            $balance = \Modules\Companies\Models\StakeholderFinancialBalance::updateOrCreate(
                [
                    'company_id' => $staff->active_company_id ?? $party->active_company_id,
                    'user_id' => $party->id,
                    'relation_type' => 'receivable',
                ],
                [
                    'created_by' => Auth::id() ?? $staff->id,
                ]
            );
            $balanceBefore = (float)$balance->balance;
            $balance->balance -= $amount;
            $balance->save();

            $tx = \App\Models\Transaction::create([
                'user_id' => $party->id,
                'cashbox_id' => null,
                'created_by' => Auth::id() ?? $staff->id,
                'company_id' => $balance->company_id,
                'branch_id' => $staff->branch_id,
                'type' => 'withdraw',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balance->balance,
                'description' => "سداد مبلغ: {$amount} - {$notes}",
            ]);

            $balance->last_transaction_id = $tx->id;
            $balance->save();
        });
    }
}
