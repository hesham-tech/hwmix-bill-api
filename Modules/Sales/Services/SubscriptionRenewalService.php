<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Subscription;
use Modules\Sales\Models\SubscriptionPayment;
use App\Models\CashBox;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SubscriptionRenewalService
{
    public function renew(Subscription $subscription, array $data)
    {
        return DB::transaction(function () use ($subscription, $data) {
            $amount = $data['amount'];
            $paymentMethodId = $data['payment_method_id'] ?? null;
            $cashBoxId = $data['cash_box_id'] ?? null;
            $notes = $data['notes'] ?? null;

            $pricePerPeriod = $subscription->price ?: ($subscription->service->default_price ?? 0);
            $currentBalance = $subscription->partial_payment;
            $totalAvailable = $amount + $currentBalance;

            $periodsToRenew = $pricePerPeriod > 0 ? floor($totalAvailable / $pricePerPeriod) : 1;
            $newBalance = $totalAvailable;

            if ($periodsToRenew > 0 && $pricePerPeriod > 0) {
                $cost = $periodsToRenew * $pricePerPeriod;
                $newBalance = $totalAvailable - $cost;

                $currentExpiry = $subscription->ends_at ?: $subscription->next_billing_date;
                $startDate = ($currentExpiry && Carbon::parse($currentExpiry)->isFuture()) ? Carbon::parse($currentExpiry) : Carbon::now();

                $periodUnit = $subscription->service->period_unit ?? 'month';
                $periodValue = ($subscription->service->period_value ?? 1) * $periodsToRenew;

                $newExpiry = $this->calculateNewExpiry($startDate, $periodUnit, $periodValue);
                $subscription->ends_at = $newExpiry;
                $subscription->next_billing_date = $newExpiry->toDateString();
                $subscription->status = 'active';
            }

            $subscription->partial_payment = $newBalance;
            $subscription->save();

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

            if ($cashBoxId && $amount > 0) $this->recordCashTransaction($payment);

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
        return match ($unit) {
            'week' => $date->addWeeks($value),
            'quarter' => $date->addMonths($value * 3),
            'year' => $date->addYears($value),
            default => $date->addMonths($value),
        };
    }

    private function recordCashTransaction(SubscriptionPayment $payment)
    {
        $cashBox = CashBox::find($payment->cash_box_id);
        if (!$cashBox) return;

        $balanceBefore = $cashBox->balance;
        $balanceAfter = $balanceBefore + $payment->amount;

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

        $cashBox->increment('balance', (float) $payment->amount);
    }
}
