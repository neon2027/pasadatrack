<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\BiometricDevice;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Attendance\BiometricEvent;
use App\Models\Fleet\Driver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_pairs_known_users_and_deduplicates_replays(): void
    {
        [$user, $device, $driver] = $this->enrolledDevice();
        $events = [$this->scan('scan-in', 'in', '2026-10-10T06:00:00+08:00'), $this->scan('scan-out', 'out', '2026-10-10T14:00:00+08:00')];
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => $events])->assertSessionHasNoErrors();

        $this->post(route('devices.import', [$user->currentTeam, $device]), ['events' => $events])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('biometric_events', 2);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseHas('attendance_sessions', ['driver_id' => $driver->id, 'source' => 'biometric', 'in_at' => '2026-10-09 22:00:00', 'out_at' => '2026-10-10 06:00:00']);
        $this->assertDatabaseCount('driving_records', 0);
        $this->assertDatabaseCount('boundary_obligations', 0);
    }

    public function test_processing_does_not_skip_scans_after_the_first_200_rows(): void
    {
        [$user, $device] = $this->enrolledDevice();
        $start = CarbonImmutable::parse('2026-10-10T06:00:00+08:00');
        $events = [];
        for ($index = 0; $index < 150; $index++) {
            $events[] = $this->scan('in-'.$index, 'in', $start->addMinutes($index * 2)->format('Y-m-d\\TH:i:sP'));
            $events[] = $this->scan('out-'.$index, 'out', $start->addMinutes($index * 2 + 1)->format('Y-m-d\\TH:i:sP'));
        }

        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => $events])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('biometric_events', 300);
        $this->assertDatabaseCount('attendance_sessions', 150);
        $this->assertSame(0, AttendanceSession::query()->whereNull('out_at')->count());
    }

    public function test_unknown_scan_is_preserved_and_can_be_processed_after_matching(): void
    {
        $user = User::factory()->create();
        $device = BiometricDevice::factory()->create(['team_id' => $user->current_team_id]);
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('unknown', 'in', '2026-10-10T06:00:00+08:00')]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_sessions', 0);
        $this->post(route('devices.enroll', [$user->currentTeam, $device]), ['external_user_id' => '42', 'driver_id' => $driver->id])->assertSessionHasNoErrors();

        $this->post(route('devices.process', [$user->currentTeam, $device]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('biometric_events', 1);
        $this->assertDatabaseHas('attendance_sessions', ['driver_id' => $driver->id, 'source' => 'biometric']);
    }

    public function test_conflicting_replayed_event_key_is_rejected(): void
    {
        [$user, $device] = $this->enrolledDevice();
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('key', 'in', '2026-10-10T06:00:00+08:00')]])->assertSessionHasNoErrors();

        $this->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('key', 'in', '2026-10-10T07:00:00+08:00')]])->assertSessionHasErrors(['events']);

        $this->assertDatabaseCount('biometric_events', 1);
        $this->assertSame('2026-10-09 22:00:00', BiometricEvent::query()->sole()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_out_of_order_delivery_is_processed_when_check_in_arrives(): void
    {
        [$user, $device] = $this->enrolledDevice();
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('out', 'out', '2026-10-10T14:00:00+08:00')]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_sessions', 0);

        $this->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('in', 'in', '2026-10-10T06:00:00+08:00')]])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertSame('2026-10-10 06:00:00', AttendanceSession::query()->sole()->out_at->format('Y-m-d H:i:s'));
    }

    public function test_corrected_biometric_scans_cannot_be_reprocessed(): void
    {
        [$user, $device, $driver] = $this->enrolledDevice();
        $events = [$this->scan('in', 'in', '2026-10-10T06:00:00+08:00'), $this->scan('out', 'out', '2026-10-10T14:00:00+08:00')];
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => $events])->assertSessionHasNoErrors();
        $session = AttendanceSession::query()->sole();
        $this->patch(route('attendance.update', [$user->currentTeam, $session]), ['driver_id' => $driver->id, 'in_at' => '2026-10-10T06:05', 'out_at' => '2026-10-10T14:05', 'reason' => 'Verified log'])->assertSessionHasNoErrors();

        $this->post(route('devices.process', [$user->currentTeam, $device]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_corrections', 1);
        $this->assertSame($session->in_event_id, $session->fresh()->in_event_id);
        $this->assertSame('2026-10-09 22:05:00', $session->fresh()->in_at->format('Y-m-d H:i:s'));
    }

    public function test_conflicting_scans_remain_pending_instead_of_overwriting_manual_attendance(): void
    {
        [$user, $device, $driver] = $this->enrolledDevice();
        AttendanceSession::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $driver->id, 'source' => 'manual', 'in_at' => '2026-10-09 22:00:00']);

        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('in', 'in', '2026-10-10T06:01:00+08:00')]])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('biometric_events', 1);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertSame(1, BiometricEvent::query()->whereDoesntHave('inSessions')->whereDoesntHave('outSessions')->count());
    }

    public function test_inactive_device_cannot_import_scans(): void
    {
        [$user, $device] = $this->enrolledDevice();
        $device->update(['is_active' => false]);

        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('in', 'in', '2026-10-10T06:00:00+08:00')]])->assertNotFound();

        $this->assertDatabaseCount('biometric_events', 0);
    }

    public function test_device_matching_rejects_drivers_from_another_team(): void
    {
        $user = User::factory()->create();
        $device = BiometricDevice::factory()->create(['team_id' => $user->current_team_id]);
        $driver = Driver::factory()->create();

        $this->actingAs($user)->post(route('devices.enroll', [$user->currentTeam, $device]), ['external_user_id' => '42', 'driver_id' => $driver->id])->assertSessionHasErrors(['driver_id']);

        $this->assertDatabaseCount('biometric_device_users', 0);
    }

    private function enrolledDevice(): array
    {
        $user = User::factory()->create();
        $device = BiometricDevice::factory()->create(['team_id' => $user->current_team_id]);
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        BiometricDeviceUser::query()->create(['team_id' => $user->current_team_id, 'biometric_device_id' => $device->id, 'external_user_id' => '42', 'driver_id' => $driver->id]);

        return [$user, $device, $driver];
    }

    public function test_revocation_preserves_history_and_leaves_future_scans_pending(): void
    {
        $this->travelTo('2026-10-10 08:00:00');
        [$user, $device, $driver] = $this->enrolledDevice();
        $mapping = BiometricDeviceUser::query()->sole();
        $this->actingAs($user)->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [
            $this->scan('in', 'in', '2026-10-10T06:00:00+08:00'), $this->scan('out', 'out', '2026-10-10T14:00:00+08:00'),
        ]])->assertSessionHasNoErrors();
        $this->post(route('devices.revoke', [$user->currentTeam, $device, $mapping]), ['reason' => 'Driver left operator'])->assertSessionHasNoErrors();

        $this->post(route('devices.import', [$user->currentTeam, $device]), ['events' => [$this->scan('next-in', 'in', '2026-10-11T06:00:00+08:00')]])->assertSessionHasNoErrors();

        $this->assertNotNull($mapping->fresh()->revoked_at);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('biometric_events', 3);
        $this->assertDatabaseHas('audit_entries', ['action' => 'device.enrollment-revoked', 'reason' => 'Driver left operator']);
    }

    private function scan(string $key, string $direction, string $time): array
    {
        return ['event_key' => $key, 'external_user_id' => '42', 'direction' => $direction, 'occurred_at' => $time];
    }
}
