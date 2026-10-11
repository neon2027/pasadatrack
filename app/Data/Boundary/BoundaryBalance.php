<?php

namespace App\Data\Boundary;

use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;

class BoundaryBalance
{
    /** @return array{original: string, adjusted: string, paid: string, outstanding: string} */
    public function obligation(BoundaryObligation $obligation): array
    {
        $obligation->loadMissing(['adjustments', 'allocations.payment.reversal']);
        $original = Money::cents($obligation->original_amount);
        $adjusted = $original + $obligation->adjustments->sum(fn ($adjustment): int => Money::cents($adjustment->amount));
        $paid = $obligation->allocations->sum(fn ($allocation): int => $allocation->payment->reversal ? 0 : Money::cents($allocation->amount));

        return ['original' => Money::decimal($original), 'adjusted' => Money::decimal($adjusted), 'paid' => Money::decimal($paid), 'outstanding' => Money::decimal($adjusted - $paid)];
    }

    public function available(Payment $payment): string
    {
        $payment->loadMissing(['allocations', 'reversal']);

        return $payment->reversal ? '0.00' : Money::decimal(Money::cents($payment->amount) - $payment->allocations->sum(fn ($allocation): int => Money::cents($allocation->amount)));
    }
}
