<?php

namespace App\Models\Boundary;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $payment_id
 * @property string $reason
 * @property int $reversed_by
 * @property CarbonImmutable $reversed_at
 */
#[Fillable(['team_id', 'payment_id', 'reason', 'reversed_by', 'reversed_at'])]
class PaymentReversal extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['reversed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
