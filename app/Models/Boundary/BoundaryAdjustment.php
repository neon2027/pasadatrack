<?php

namespace App\Models\Boundary;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Approved facts only; amount is signed (positive increases, negative decreases).
 * Pending requests and approval authorization belong outside this ledger table.
 *
 * @property int $boundary_obligation_id
 * @property string $amount
 * @property string $reason
 * @property int $approved_by
 * @property CarbonImmutable $approved_at
 * @property string|null $submission_id
 */
#[Fillable(['team_id', 'boundary_obligation_id', 'amount', 'reason', 'approved_by', 'approved_at', 'submission_id'])]
class BoundaryAdjustment extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BoundaryObligation, $this> */
    public function boundaryObligation(): BelongsTo
    {
        return $this->belongsTo(BoundaryObligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
