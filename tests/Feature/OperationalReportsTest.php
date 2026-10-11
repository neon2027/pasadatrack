<?php

namespace Tests\Feature;

use App\Actions\Scheduling\ManageAssignments;
use App\Models\Boundary\Payment;
use App\Models\Fleet\Driver;
use App\Models\Scheduling\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_includes_both_ends_of_a_local_date_range(): void
    {
        $user = User::factory()->create();
        $shift = Assignment::factory()->create(['team_id' => $user->current_team_id, 'operational_date' => '2026-10-10']);
        $shift->route->update(['default_boundary_amount' => '1200.50']);
        app(ManageAssignments::class)->confirm($user->currentTeam, $user, $shift, $shift->planned_driver_id);
        Payment::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $shift->planned_driver_id, 'amount' => '500.25', 'received_by' => $user->id, 'collected_at' => '2026-10-09 16:00:00', 'receipt_number' => 'R01']);
        Payment::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $shift->planned_driver_id, 'amount' => '99.00', 'received_by' => $user->id, 'collected_at' => '2026-10-10 16:00:00', 'receipt_number' => 'R02']);

        $this->actingAs($user)->get(route('reports.index', [$user->currentTeam, 'from' => '2026-10-10', 'to' => '2026-10-10']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('reports/index')
            ->where('totals.adjusted', '1200.50')->where('totals.collected', '500.25')->where('totals.outstanding', '1200.50')->has('rows.data', 1));

        $this->assertDatabaseCount('payments', 2);
    }

    public function test_export_excludes_other_teams_and_neutralizes_csv_formulas(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id, 'name' => '=1+1']);
        Payment::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $driver->id, 'amount' => '100.00', 'received_by' => $user->id, 'collected_at' => '2026-10-10 01:00:00', 'receipt_number' => 'R01']);
        $other = User::factory()->create();
        $otherDriver = Driver::factory()->create(['team_id' => $other->current_team_id, 'name' => 'Foreign driver']);
        Payment::query()->create(['team_id' => $other->current_team_id, 'driver_id' => $otherDriver->id, 'amount' => '900.00', 'received_by' => $other->id, 'collected_at' => '2026-10-10 01:00:00', 'receipt_number' => 'R02']);

        $response = $this->actingAs($user)->get(route('reports.export', [$user->currentTeam, 'from' => '2026-10-10', 'to' => '2026-10-10', 'format' => 'csv']));

        $response->assertOk()->assertDownload('PasadaTrack_Operations.csv');
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
        $this->assertStringNotContainsString('Foreign driver', $response->streamedContent());
    }

    public function test_excel_export_escapes_names_and_contains_numeric_amounts(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id, 'name' => 'Maria & Sons <test>']);
        Payment::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $driver->id, 'amount' => '100.25', 'received_by' => $user->id, 'collected_at' => '2026-10-10 01:00:00', 'receipt_number' => 'R01']);

        $response = $this->actingAs($user)->get(route('reports.export', [$user->currentTeam, 'from' => '2026-10-10', 'to' => '2026-10-10', 'format' => 'excel']));

        $response->assertOk()->assertDownload('PasadaTrack_Operations.xml');
        $this->assertStringContainsString('Maria &amp; Sons &lt;test&gt;', $response->streamedContent());
        $this->assertStringContainsString('ss:Type="Number">100.25', $response->streamedContent());
    }

    public function test_report_rejects_reversed_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index', [$user->currentTeam, 'from' => '2026-10-11', 'to' => '2026-10-10']))
            ->assertSessionHasErrors(['to']);
    }
}
