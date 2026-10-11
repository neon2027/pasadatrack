<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\ExportOperationalReport;
use App\Data\OperationsTime;
use App\Data\Reports\OperationalReport;
use App\Http\Controllers\Controller;
use App\Models\Fleet\Driver;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Team $current_team, OperationalReport $report): Response
    {
        $filters = $this->filters($request);
        $result = $report->build($current_team, $filters['from'], $filters['to'], isset($filters['driver_id']) ? (int) $filters['driver_id'] : null);
        $page = max(1, (int) $request->query('page', 1));
        $records = new LengthAwarePaginator(array_slice($result['rows'], ($page - 1) * 15, 15), count($result['rows']), 15, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return Inertia::render('reports/index', [
            'rows' => $records, 'totals' => $result['totals'], 'filters' => $filters,
            'drivers' => Driver::forTeam($current_team)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, Team $current_team, OperationalReport $report, ExportOperationalReport $export): StreamedResponse
    {
        $filters = $this->filters($request);
        $data = $request->validate(['format' => ['required', 'in:csv,excel']]);
        $result = $report->build($current_team, $filters['from'], $filters['to'], isset($filters['driver_id']) ? (int) $filters['driver_id'] : null);

        return $export->download($result['rows'], $data['format']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'driver_id' => ['nullable', 'integer'],
        ]);
        $data['from'] = $data['from'] ?? now(OperationsTime::TIMEZONE)->startOfMonth()->toDateString();
        $data['to'] = $data['to'] ?? now(OperationsTime::TIMEZONE)->toDateString();
        $from = OperationsTime::utc($data['from'].'T00:00');
        $to = OperationsTime::utc($data['to'].'T00:00');
        if ($to->isBefore($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered date range of up to 366 days.']);
        }

        return $data;
    }
}
