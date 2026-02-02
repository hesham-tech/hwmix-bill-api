<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\CashBox;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SubscriptionRenewalService
{
    /**
     * Renew a subscription with a payment
     */
    public function renew(Subscription $subscription, array $data)
    {
        return DB::transaction(function () use ($subscription, $data) {
            $amount = $data['amount'];
            $paymentMethodId = $data['payment_method_id'] ?? null;
            $cashBoxId = $data['cash_box_id'] ?? null;
            $notes = $data['notes'] ?? null;

            $pricePerPeriod = $subscription->price;
            if ($pricePerPeriod <= 0) {
                // If price is 0, we just renew for one period if amount is 0, 
                // but usually price should be defined in the subscription or service.
                $pricePerPeriod = $subscription->service->default_price ?? 0;
            }

            $currentBalance = $subscription->partial_payment;
            $totalAvailable = $amount + $currentBalance;

            // Calculate how many periods can be renewed
            // For now, let's assume 1 period per pricePerPeriod
            // We can make this more complex later if needed
            $periodsToRenew = 0;
            if ($pricePerPeriod > 0) {
                $periodsToRenew = floor($totalAvailable / $pricePerPeriod);
            } else {
                $periodsToRenew = 1; // Default to 1 if free?
            }

            $newBalance = $totalAvailable;

            if ($periodsToRenew > 0 && $pricePerPeriod > 0) {
                $cost = $periodsToRenew * $pricePerPeriod;
                $newBalance = $totalAvailable - $cost;

                // Update expiry date
                $currentExpiry = $subscription->ends_at ?: $subscription->next_billing_date;
                $startDate = Carbon::now();

                // If not expired yet, extend from current expiry
                if ($currentExpiry && Carbon::parse($currentExpiry)->isFuture()) {
                    $startDate = Carbon::parse($currentExpiry);
                }

                $periodUnit = $subscription->service->period_unit ?? 'month';
                $periodValue = ($subscription->service->period_value ?? 1) * $periodsToRenew;

                $newExpiry = $this->calculateNewExpiry($startDate, $periodUnit, $periodValue);

                $subscription->ends_at = $newExpiry;
                $subscription->next_billing_date = $newExpiry->toDateString();
                $subscription->status = 'active';
            }

            $subscription->partial_payment = $newBalance;
            $subscription->save();

            // Record Payment
            $payment = SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'user_id' => $subscription->user_id,
                'created_by' => Auth::id(),
                'amount' => $amount,
                'partial_payment_before' => $currentBalance,
                'partial_payment_after' => $newBalance,
                'payment_date' => Carbon::now(),
                'payment_method_id' => $paymentMethodId,
                'cash_box_id' => $cashBoxId,
                'notes' => $notes,
            ]);

            // If cash box is provided, create a transaction
            if ($cashBoxId && $amount > 0) {
                $this->recordCashTransaction($payment);
            }

            return [
                'subscription' => $subscription,
                'payment' => $payment,
                'periods_renewed' => $periodsToRenew
            ];
        });
    }

    private function calculateNewExpiry(Carbon $start, string $unit, int $value): Carbon
    {
        $date = $start->copy();
        switch ($unit) {
            case 'week':
                return $date->addWeeks($value);
            case 'quarter':
                return $date->addMonths($value * 3);
            case 'year':
                return $date->addYears($value);
            case 'month':
            default:
                return $date->addMonths($value);
        }
    }

    private function recordCashTransaction(SubscriptionPayment $payment)
    {
        $cashBox = CashBox::find($payment->cash_box_id);
        if (!$cashBox)
            return;

        $balanceBefore = $cashBox->balance;
        $balanceAfter = $balanceBefore + $payment->amount;

        // Create transaction record
        Transaction::create([
            'cashbox_id' => $payment->cash_box_id,
            'user_id' => $payment->user_id,
            'company_id' => $payment->company_id,
            'amount' => $payment->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'type' => 'deposit',
            'description' => "تجديد اشتراك: " . ($payment->subscription->service->name ?? 'خدمة') . " - " . ($payment->subscription->user->nickname ?? $payment->subscription->user->full_name ?? 'عميل'),
            'created_by' => $payment->created_by,
        ]);

        // Update cash box balance
        $cashBox->increment('balance', (float) $payment->amount);
    }
}
