<?php

namespace App\Models\Boundary;

use App\Models\Fleet\Driver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Services enforce positive amounts, payment/obligation limits, reversal exclusion
 * and locking. Composite FKs enforce the same team and driver on both parents.
 *
 * @property int $payment_id
 * @property int $boundary_obligation_id
 * @property int $driver_id
 * @property string $amount
 */
#[Fillable(['team_id', 'payment_id', 'boundary_obligation_id', 'driver_id', 'amount'])]
class PaymentAllocation extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<BoundaryObligation, $this> */
    public function boundaryObligation(): BelongsTo
    {
        return $this->belongsTo(BoundaryObligation::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
