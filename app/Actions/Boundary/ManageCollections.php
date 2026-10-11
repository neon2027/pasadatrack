<?php

namespace App\Actions\Boundary;

use App\Actions\Audit\RecordAuditEntry;
use App\Data\Boundary\BoundaryBalance;
use App\Data\Boundary\Money;
use App\Data\OperationsTime;
use App\Models\Boundary\BoundaryAdjustment;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Boundary\PaymentAllocation;
use App\Models\Boundary\PaymentReversal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageCollections
{
    public function __construct(private RecordAuditEntry $audit, private BoundaryBalance $balance) {}

    /** @param array<string, mixed> $data */
    public function collect(Team $team, User $actor, array $data): void
    {
        DB::transaction(function () use ($team, $actor, $data): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            if (Money::cents($data['amount']) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
            }
            if (OperationsTime::utc($data['collected_at'])->isFuture()) {
                throw ValidationException::withMessages(['collected_at' => 'Collection time cannot be in the future.']);
            }
            if (Payment::forTeam($team)->where('receipt_number', $data['receipt_number'])->exists()) {
                throw ValidationException::withMessages(['receipt_number' => 'This receipt number has already been recorded.']);
            }
            $payment = Payment::query()->create([
                'team_id' => $team->id, 'driver_id' => $data['driver_id'], 'amount' => $data['amount'],
                'collected_at' => OperationsTime::utc($data['collected_at']), 'received_by' => $actor->id,
                'receipt_number' => $data['receipt_number'],
            ]);
            $this->audit->handle($actor, $payment, 'payment.collected');
        }, 5);
    }

    public function allocate(Team $team, User $actor, Payment $payment, int $obligationId, string $amount): void
    {
        DB::transaction(function () use ($team, $actor, $payment, $obligationId, $amount): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::forTeam($team)->lockForUpdate()->findOrFail($payment->id);
            $obligation = BoundaryObligation::forTeam($team)->lockForUpdate()->findOrFail($obligationId);
            if ($payment->driver_id !== $obligation->driver_id) {
                throw ValidationException::withMessages(['boundary_obligation_id' => 'Allocate only to obligations belonging to the same driver.']);
            }
            if ($payment->reversal()->exists()) {
                throw ValidationException::withMessages(['amount' => 'A reversed payment cannot be allocated.']);
            }
            $cents = Money::cents($amount);
            if ($cents <= 0 || $cents > Money::cents($this->balance->available($payment))) {
                throw ValidationException::withMessages(['amount' => 'Allocation must be positive and cannot exceed the payment balance.']);
            }
            if ($cents > Money::cents($this->balance->obligation($obligation)['outstanding'])) {
                throw ValidationException::withMessages(['amount' => 'Allocation cannot exceed the outstanding boundary.']);
            }
            if ($payment->allocations()->where('boundary_obligation_id', $obligation->id)->exists()) {
                throw ValidationException::withMessages(['boundary_obligation_id' => 'This receipt has already been allocated to that obligation. Use another receipt for an additional payment.']);
            }
            $allocation = PaymentAllocation::query()->create([
                'team_id' => $team->id, 'payment_id' => $payment->id, 'boundary_obligation_id' => $obligation->id,
                'driver_id' => $payment->driver_id, 'amount' => $amount,
            ]);
            $this->audit->handle($actor, $allocation, 'payment.allocated');
        }, 5);
    }

    public function adjust(Team $team, User $actor, BoundaryObligation $obligation, string $amount, string $reason, string $submissionId): void
    {
        DB::transaction(function () use ($team, $actor, $obligation, $amount, $reason, $submissionId): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $obligation = BoundaryObligation::forTeam($team)->lockForUpdate()->findOrFail($obligation->id);
            $existing = BoundaryAdjustment::forTeam($team)->where('submission_id', $submissionId)->first();
            if ($existing !== null) {
                if ($existing->boundary_obligation_id !== $obligation->id || Money::cents($existing->amount) !== Money::cents($amount) || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['amount' => 'This approval was already used for a different adjustment. Close the form and start a new adjustment.']);
                }

                return;
            }
            $balance = $this->balance->obligation($obligation);
            $cents = Money::cents($amount);
            $adjusted = Money::cents($balance['adjusted']) + $cents;
            if ($cents === 0 || $adjusted < Money::cents($balance['paid']) || $adjusted > 999999999999) {
                throw ValidationException::withMessages(['amount' => 'The adjusted boundary must cover payments already allocated and stay within the supported amount limit.']);
            }
            $adjustment = BoundaryAdjustment::query()->create([
                'team_id' => $team->id, 'boundary_obligation_id' => $obligation->id, 'amount' => $amount,
                'reason' => $reason, 'approved_by' => $actor->id, 'approved_at' => now('UTC'),
                'submission_id' => $submissionId,
            ]);
            $this->audit->handle($actor, $adjustment, 'boundary.adjusted', reason: $reason);
        }, 5);
    }

    public function reverse(Team $team, User $actor, Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($team, $actor, $payment, $reason): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::forTeam($team)->lockForUpdate()->findOrFail($payment->id);
            if ($payment->reversal()->exists()) {
                throw ValidationException::withMessages(['reason' => 'This payment has already been reversed.']);
            }
            $reversal = PaymentReversal::query()->create([
                'team_id' => $team->id, 'payment_id' => $payment->id, 'reason' => $reason,
                'reversed_by' => $actor->id, 'reversed_at' => now('UTC'),
            ]);
            $this->audit->handle($actor, $reversal, 'payment.reversed', reason: $reason);
        }, 5);
    }
}
