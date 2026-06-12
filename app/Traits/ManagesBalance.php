<?php
//   تريت لإدارة حركات الأرصدة للمستخدم وحماية عمليات السحب والإيداع والتحويل
namespace App\Traits;

use App\Models\CashBox;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait ManagesBalance
 * مسؤول عن كافة العمليات المالية المرتبطة بالمستخدم وخزنته.
 */
trait ManagesBalance
{
    /**
     * خصم مبلغ من رصيد المستخدم (خزنته).
     *
     * @param float $amount المبلغ المراد سحبه.
     * @param int|null $cashBoxId معرف صندوق النقدية المحدد (اختياري).
     * @return bool
     * @throws Exception
     */
    public function withdraw(float $amount, $cashBoxId = null, $description = null, $log = true, array $options = []): bool
    {
        $amount = floatval($amount);
        $authCompanyId = Auth::user()->active_company_id ?? null;
        $companyId = $options['company_id'] ?? $authCompanyId;

        Transaction::$preventObserverLog = true;
        DB::beginTransaction();
        try {
            $cashBox = null;

            if ($cashBoxId) {
                $cashBox = CashBox::withoutGlobalScopes()
                    ->where('id', $cashBoxId)
                    ->where('user_id', $this->id)
                    ->first();
            } else {
                if (is_null($companyId)) {
                    DB::rollBack();
                    throw new Exception("لا توجد شركة نشطة لتحديد الخزنة الافتراضية للمستخدم: {$this->nickname}");
                }
                $cashBox = CashBox::withoutGlobalScopes()
                    ->where('user_id', $this->id)
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->first() ?? CashBox::withoutGlobalScopes()
                        ->where('user_id', $this->id)
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->first();
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new Exception("لم يتم العثور على خزنة مناسبة للمستخدم: {$this->nickname}");
            }

            $balanceBefore = $cashBox->balance;
            $cashBox->balance -= $amount;
            $cashBox->save();
            $balanceAfter = $cashBox->balance;

            if ($log) {
                Transaction::create([
                    'user_id' => $this->id,
                    'cashbox_id' => $cashBox->id,
                    'created_by' => $options['created_by'] ?? Auth::id() ?? $this->id,
                    'company_id' => $cashBox->company_id,
                    'type' => 'withdraw',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'employee_balance_before' => $options['employee_balance_before'] ?? null,
                    'employee_balance_after' => $options['employee_balance_after'] ?? null,
                    'client_balance_before' => $options['client_balance_before'] ?? null,
                    'client_balance_after' => $options['client_balance_after'] ?? null,
                    'source_invoice_id' => $options['source_invoice_id'] ?? null,
                    'source_installment_id' => $options['source_installment_id'] ?? null,
                    'is_transfer' => $options['is_transfer'] ?? false,
                    'description' => $description ?? 'سحب نقدي',
                ]);
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ManagesBalance Trait Withdraw: فشل السحب.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
                'cash_box_id' => $cashBoxId,
            ]);
            throw $e;
        } finally {
            Transaction::$preventObserverLog = false;
        }
    }

    /**
     * إيداع مبلغ في رصيد المستخدم (خزنته).
     *
     * @param float $amount المبلغ المراد إيداعه.
     * @param int|null $cashBoxId معرف صندوق النقدية المحدد (اختياري).
     * @return bool
     * @throws Exception
     */
    public function deposit(float $amount, $cashBoxId = null, $description = null, $log = true, array $options = []): bool
    {
        $amount = floatval($amount);
        Transaction::$preventObserverLog = true;
        DB::beginTransaction();
        $authCompanyId = Auth::user()->active_company_id ?? null;
        $companyId = $options['company_id'] ?? $authCompanyId;

        try {
            $cashBox = null;
            if ($cashBoxId) {
                $cashBox = CashBox::withoutGlobalScopes()
                    ->where('id', $cashBoxId)
                    ->where('user_id', $this->id)
                    ->first();

                if (!$cashBox) {
                    DB::rollBack();
                    throw new Exception("معرف الخزنة {$cashBoxId} غير صالح أو لا ينتمي للمستخدم {$this->nickname}.");
                }
            } else {
                if (is_null($companyId)) {
                    DB::rollBack();
                    throw new Exception("لا توجد شركة نشطة لتحديد الخزنة الافتراضية للمستخدم {$this->nickname}.");
                }
                $cashBox = CashBox::withoutGlobalScopes()
                    ->where('user_id', $this->id)
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->first() ?? CashBox::withoutGlobalScopes()
                        ->where('user_id', $this->id)
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->first();

                if (!$cashBox) {
                    DB::rollBack();
                    throw new Exception("المستخدم {$this->nickname} ليس له خزنة في الشركة النشطة.");
                }
            }

            $balanceBefore = $cashBox->balance;
            $cashBox->balance += $amount;
            $cashBox->save();
            $balanceAfter = $cashBox->balance;

            if ($log) {
                Transaction::create([
                    'user_id' => $this->id,
                    'cashbox_id' => $cashBox->id,
                    'created_by' => $options['created_by'] ?? Auth::id() ?? $this->id,
                    'company_id' => $cashBox->company_id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'employee_balance_before' => $options['employee_balance_before'] ?? null,
                    'employee_balance_after' => $options['employee_balance_after'] ?? null,
                    'client_balance_before' => $options['client_balance_before'] ?? null,
                    'client_balance_after' => $options['client_balance_after'] ?? null,
                    'source_invoice_id' => $options['source_invoice_id'] ?? null,
                    'source_installment_id' => $options['source_installment_id'] ?? null,
                    'is_transfer' => $options['is_transfer'] ?? false,
                    'description' => $description ?? 'إيداع نقدي',
                ]);
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ManagesBalance Trait Deposit: فشل الإيداع.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
                'cash_box_id' => $cashBoxId,
            ]);
            throw $e;
        } finally {
            Transaction::$preventObserverLog = false;
        }
    }

    /**
     * تحويل مبلغ من صندوق نقدي للمستخدم الحالي إلى مستخدم آخر في نفس الشركة.
     */
    public function transfer($cashBoxId, $targetUserId, $amount, $description = null): bool
    {
        $amount = floatval($amount);

        if (!$this->hasAnyPermission(['admin.super', 'transfer'])) {
            throw new Exception('Unauthorized: You do not have permission to transfer.');
        }

        Transaction::$preventObserverLog = true;
        DB::beginTransaction();
        try {
            $authCompanyId = Auth::user()->active_company_id ?? null;
            if (is_null($authCompanyId)) {
                throw new Exception("لا توجد شركة نشطة لإجراء التحويل.");
            }

            $cashBox = $this->cashBoxes()
                ->where('id', $cashBoxId)
                ->where('company_id', $authCompanyId)
                ->firstOrFail();

            if ($cashBox->balance < $amount) {
                throw new Exception('Insufficient funds in the cash box.');
            }

            $targetUser = User::findOrFail($targetUserId);
            $targetCashBox = $targetUser->cashBoxes()
                ->where('cash_type', $cashBox->cash_type)
                ->where('company_id', $authCompanyId)
                ->first();

            if (!$targetCashBox) {
                throw new Exception('Target user does not have a matching cash box in the active company.');
            }

            $cashBox->balance -= $amount;
            $cashBox->save();

            $targetCashBox->balance += $amount;
            $targetCashBox->save();

            $senderTransaction = Transaction::create([
                'user_id' => $this->id,
                'cashbox_id' => $cashBox->id,
                'target_user_id' => $targetUserId,
                'target_cashbox_id' => $targetCashBox->id,
                'created_by' => $this->id,
                'company_id' => $authCompanyId,
                'type' => 'transfer_out',
                'amount' => $amount,
                'balance_before' => $cashBox->balance + $amount,
                'balance_after' => $cashBox->balance,
                'description' => $description,
            ]);

            Transaction::create([
                'user_id' => $targetUserId,
                'cashbox_id' => $targetCashBox->id,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $cashBox->id,
                'created_by' => $this->id,
                'company_id' => $authCompanyId,
                'type' => 'transfer_in',
                'amount' => $amount,
                'balance_before' => $targetCashBox->balance - $amount,
                'balance_after' => $targetCashBox->balance,
                'description' => 'Received from transfer',
                'original_transaction_id' => $senderTransaction->id,
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ManagesBalance Trait Transfer: فشل التحويل.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
            ]);
            throw $e;
        } finally {
            Transaction::$preventObserverLog = false;
        }
    }

    /**
     * تحويل مباشر لمستخدم محدد (تبسيط للعملية).
     */
    public function transferTo(User $targetUser, float $amount, int $fromCashBoxId, int $toCashBoxId, $description = null): bool
    {
        $amount = floatval($amount);
        Transaction::$preventObserverLog = true;
        DB::beginTransaction();
        try {
            $fromCashBox = CashBox::findOrFail($fromCashBoxId);
            $toCashBox = CashBox::findOrFail($toCashBoxId);

            if ($fromCashBox->balance < $amount) {
                throw new Exception('الرصيد غير كاف');
            }

            $balanceBeforeFrom = $fromCashBox->balance;
            $balanceBeforeTo = $toCashBox->balance;

            $fromCashBox->decrement('balance', $amount);
            $toCashBox->increment('balance', $amount);

            Transaction::create([
                'user_id' => $this->id,
                'cashbox_id' => $fromCashBox->id,
                'target_user_id' => $targetUser->id,
                'target_cashbox_id' => $toCashBox->id,
                'created_by' => Auth::id() ?? $this->id,
                'company_id' => $fromCashBox->company_id,
                'type' => 'transfer_out',
                'amount' => $amount,
                'balance_before' => $balanceBeforeFrom,
                'balance_after' => $fromCashBox->fresh()->balance,
                'description' => $description ?? 'تحويل للأرصدة'
            ]);

            Transaction::create([
                'user_id' => $targetUser->id,
                'cashbox_id' => $toCashBox->id,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $fromCashBox->id,
                'created_by' => Auth::id() ?? $this->id,
                'company_id' => $toCashBox->company_id,
                'type' => 'transfer_in',
                'amount' => $amount,
                'balance_before' => $balanceBeforeTo,
                'balance_after' => $toCashBox->fresh()->balance,
                'description' => $description ?? 'استلام أرصدة'
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ManagesBalance Trait TransferTo: فشل التحويل.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'target_id' => $targetUser->id,
                'amount' => $amount,
            ]);
            throw $e;
        } finally {
            Transaction::$preventObserverLog = false;
        }
    }
}
