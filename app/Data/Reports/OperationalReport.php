<?php

namespace App\Data\Reports;

use App\Data\Boundary\BoundaryBalance;
use App\Data\Boundary\Money;
use App\Data\OperationsTime;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Team;

class OperationalReport
{
    public function __construct(private BoundaryBalance $balance) {}

    /** @return array{rows: array<int, array<string, mixed>>, totals: array<string, string>} */
    public function build(Team $team, string $from, string $to, ?int $driverId): array
    {
        $rows = [];
        $obligations = BoundaryObligation::forTeam($team)->with(['driver', 'adjustments', 'allocations.payment.reversal'])
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->whereHas('drivingRecord', fn ($query) => $query->whereDate('driving_date', '>=', $from)->whereDate('driving_date', '<=', $to))->get();
        foreach ($obligations as $obligation) {
            $rows[$obligation->driver_id] ??= $this->emptyRow($obligation->driver_id, $obligation->driver->name);
            $row = &$rows[$obligation->driver_id];
            $balance = $this->balance->obligation($obligation);
            $row['shifts']++;
            foreach (['adjusted', 'paid', 'outstanding'] as $field) {
                $row[$field] += Money::cents($balance[$field]);
            }
            unset($row);
        }
        $payments = Payment::forTeam($team)->with(['driver', 'allocations', 'reversal'])
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->where('collected_at', '>=', OperationsTime::utc($from.'T00:00'))
            ->where('collected_at', '<', OperationsTime::utc($to.'T00:00')->addDay())->get();
        foreach ($payments as $payment) {
            $rows[$payment->driver_id] ??= $this->emptyRow($payment->driver_id, $payment->driver->name);
            if (! $payment->reversal) {
                $rows[$payment->driver_id]['collected'] += Money::cents($payment->amount);
                $rows[$payment->driver_id]['unallocated'] += Money::cents($this->balance->available($payment));
            }
        }
        $totals = ['adjusted' => 0, 'paid' => 0, 'outstanding' => 0, 'collected' => 0, 'unallocated' => 0];
        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += $row[$field];
                $row[$field] = Money::decimal($row[$field]);
            }
        }
        unset($row);
        usort($rows, fn ($first, $second): int => strcasecmp($first['driver'], $second['driver']) ?: $first['id'] <=> $second['id']);

        return ['rows' => $rows, 'totals' => array_map(Money::decimal(...), $totals)];
    }

    /** @return array<string, mixed> */
    private function emptyRow(int $id, string $name): array
    {
        return ['id' => $id, 'driver' => $name, 'shifts' => 0, 'adjusted' => 0, 'paid' => 0, 'outstanding' => 0, 'collected' => 0, 'unallocated' => 0];
    }
}
