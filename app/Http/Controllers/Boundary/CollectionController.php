<?php

namespace App\Http\Controllers\Boundary;

use App\Actions\Boundary\ManageCollections;
use App\Data\Boundary\BoundaryBalance;
use App\Data\OperationsTime;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Boundary\AdjustBoundaryRequest;
use App\Http\Requests\Boundary\AllocatePaymentRequest;
use App\Http\Requests\Boundary\ReversePaymentRequest;
use App\Http\Requests\Boundary\SavePaymentRequest;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Fleet\Driver;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request, Team $current_team, BoundaryBalance $balance): Response
    {
        $filters = $request->validate(['driver_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d']]);

        return Inertia::render('boundary/index', [
            'obligations' => BoundaryObligation::forTeam($current_team)->with(['driver', 'drivingRecord.assignment.jeepney', 'adjustments', 'allocations.payment.reversal'])
                ->when($filters['driver_id'] ?? null, fn ($query, $id) => $query->where('driver_id', $id))
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereHas('drivingRecord', fn ($query) => $query->whereDate('driving_date', $date)))
                ->latest('id')->paginate(15)->withQueryString()->through(fn ($obligation): array => [
                    'id' => $obligation->id, 'driver_id' => $obligation->driver_id, 'driver' => $obligation->driver->name,
                    'date' => $obligation->drivingRecord->driving_date->toDateString(),
                    'plate' => $obligation->drivingRecord->assignment->jeepney->plate_number,
                    ...$balance->obligation($obligation),
                ]),
            'payments' => Payment::forTeam($current_team)->with(['driver', 'allocations', 'reversal'])
                ->when($filters['driver_id'] ?? null, fn ($query, $id) => $query->where('driver_id', $id))
                ->latest('collected_at')->orderByDesc('id')->paginate(15, ['*'], 'payments_page')->withQueryString()->through(fn ($payment): array => [
                    'id' => $payment->id, 'driver_id' => $payment->driver_id, 'driver' => $payment->driver->name,
                    'amount' => $payment->amount, 'available' => $balance->available($payment),
                    'collected_at' => OperationsTime::local($payment->collected_at), 'receipt_number' => $payment->receipt_number,
                    'reversed' => $payment->reversal !== null, 'reversal_reason' => $payment->reversal?->reason,
                ]),
            'drivers' => Driver::forTeam($current_team)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters, 'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageCollections),
            'canReverse' => $request->user()->hasTeamPermission($current_team, TeamPermission::ReversePayment),
        ]);
    }

    public function store(SavePaymentRequest $request, Team $current_team, ManageCollections $action): RedirectResponse
    {
        $action->collect($current_team, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded. Allocate it to the driver’s boundary obligations.']);

        return back();
    }

    public function allocate(AllocatePaymentRequest $request, Team $current_team, Payment $payment, ManageCollections $action): RedirectResponse
    {
        $action->allocate($current_team, $request->user(), $payment, (int) $request->validated('boundary_obligation_id'), (string) $request->validated('amount'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment allocated.']);

        return back();
    }

    public function adjust(AdjustBoundaryRequest $request, Team $current_team, BoundaryObligation $obligation, ManageCollections $action): RedirectResponse
    {
        $action->adjust($current_team, $request->user(), $obligation, (string) $request->validated('amount'), $request->validated('reason'), $request->validated('submission_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Boundary adjustment recorded.']);

        return back();
    }

    public function reverse(ReversePaymentRequest $request, Team $current_team, Payment $payment, ManageCollections $action): RedirectResponse
    {
        $action->reverse($current_team, $request->user(), $payment, $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment reversed. Its allocations no longer reduce outstanding balances.']);

        return back();
    }
}
