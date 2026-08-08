<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;

/**
 * Default binding: records payments locally without contacting any provider.
 * Stripe/Paystack drivers implement the same contract when they are added.
 */
class ManualPaymentGateway implements PaymentGateway
{
    public function charge(User $user, Plan $plan, ?string $reference = null): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'amount_cents' => $plan->price_cents,
            'currency' => config('mingle.payments.currency', 'usd'),
            'status' => PaymentStatus::Pending,
            'provider' => PaymentProvider::Manual,
            'provider_reference' => $reference,
        ]);
    }

    public function settle(Payment $payment, string $reference): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => $reference,
        ]);

        return $payment->refresh();
    }
}
