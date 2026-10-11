<?php

namespace App\Models\Boundary;

use App\Models\Fleet\Driver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $driver_id
 * @property string $amount
 * @property CarbonImmutable $collected_at
 * @property int $received_by
 * @property string $receipt_number
 * @property-read Driver $driver
 * @property-read PaymentReversal|null $reversal
 */
#[Fillable(['team_id', 'driver_id', 'amount', 'collected_at', 'received_by', 'receipt_number'])]
class Payment extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'collected_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasOne<PaymentReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }
}
