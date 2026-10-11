import type { TrackedVehicle } from '@/types/tracking';

export type FleetStatus = 'recent' | 'stale' | 'no-location';

export function vehicleCoordinates(
    vehicle: TrackedVehicle,
): [number, number] | null {
    const { latitude, longitude } = vehicle;

    if (
        latitude === null ||
        longitude === null ||
        !Number.isFinite(latitude) ||
        !Number.isFinite(longitude) ||
        latitude < -90 ||
        latitude > 90 ||
        longitude < -180 ||
        longitude > 180
    ) {
        return null;
    }

    return [latitude, longitude];
}

export function vehicleStatus(
    vehicle: TrackedVehicle,
    now: number,
    freshnessSeconds: number,
): FleetStatus {
    if (vehicleCoordinates(vehicle) === null) {
        return 'no-location';
    }

    if (vehicle.recordedAt === null) {
        return 'stale';
    }

    const recordedAt = Date.parse(vehicle.recordedAt);
    const ageMilliseconds = now - recordedAt;

    return Number.isFinite(recordedAt) &&
        ageMilliseconds >= 0 &&
        ageMilliseconds <= freshnessSeconds * 1000
        ? 'recent'
        : 'stale';
}

export function filterVehicles(
    vehicles: TrackedVehicle[],
    search: string,
    status: FleetStatus | 'all',
    now: number,
    freshnessSeconds: number,
): TrackedVehicle[] {
    const query = search.trim().toLowerCase();

    return vehicles.filter((vehicle) => {
        const matchesSearch = [
            vehicle.plateNumber,
            vehicle.routeName,
            vehicle.plannedDriver,
            vehicle.actualDriver,
        ].some((value) => value?.toLowerCase().includes(query));
        const matchesStatus =
            status === 'all' ||
            vehicleStatus(vehicle, now, freshnessSeconds) === status;

        return matchesSearch && matchesStatus;
    });
}

export function vehicleSpeed(
    vehicle: TrackedVehicle,
    now: number,
    freshnessSeconds: number,
): number | null {
    const { speedKph } = vehicle;

    if (
        vehicleStatus(vehicle, now, freshnessSeconds) !== 'recent' ||
        speedKph === null ||
        !Number.isFinite(speedKph) ||
        speedKph < 0
    ) {
        return null;
    }

    return speedKph;
}

export function formatRecordedAt(
    recordedAt: string | null,
    timezone: string,
): string {
    const timestamp = recordedAt === null ? NaN : Date.parse(recordedAt);

    if (!Number.isFinite(timestamp)) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en-PH', {
        timeZone: timezone,
        dateStyle: 'medium',
        timeStyle: 'medium',
    }).format(timestamp);
}
