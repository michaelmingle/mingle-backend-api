<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;

/**
 * Payment provider seam. No gateway credentials are wired in this pass -- see
 * App\Services\Payments\ManualPaymentGateway and the README.
 */
interface PaymentGateway
{
    /** Creates a local pending payment record and returns it. */
    public function charge(User $user, Plan $plan, ?string $reference = null): Payment;

    /** Marks a payment as settled once the provider confirms it. */
    public function settle(Payment $payment, string $reference): Payment;
}
