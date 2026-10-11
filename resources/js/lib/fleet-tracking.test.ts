import { describe, expect, it } from 'vitest';
import type { TrackedVehicle } from '@/types/tracking';
import {
    filterVehicles,
    formatRecordedAt,
    vehicleCoordinates,
    vehicleSpeed,
    vehicleStatus,
} from './fleet-tracking';

const now = Date.parse('2026-10-09T00:00:00.000Z');
const freshnessSeconds = 60;

function makeVehicle(overrides: Partial<TrackedVehicle> = {}): TrackedVehicle {
    return {
        id: 'vehicle-1',
        plateNumber: 'ABC 123',
        routeName: 'Cubao - Marikina',
        plannedDriver: 'Juan Dela Cruz',
        actualDriver: 'Maria Santos',
        isReliever: true,
        latitude: 14.5995,
        longitude: 120.9842,
        speedKph: 32.5,
        recordedAt: '2026-10-08T23:59:30.000Z',
        ...overrides,
    };
}

describe('vehicleCoordinates', () => {
    it.each([
        [14.5995, 120.9842],
        [0, 0],
        [-90, -180],
        [90, 180],
    ])(
        'returns valid coordinates %s, %s in latitude-longitude order',
        (latitude, longitude) => {
            const vehicle = makeVehicle({ latitude, longitude });

            const coordinates = vehicleCoordinates(vehicle);

            expect(coordinates).toEqual([latitude, longitude]);
        },
    );

    it.each([
        { latitude: null },
        { longitude: null },
        { latitude: NaN },
        { longitude: NaN },
        { latitude: Infinity },
        { latitude: -Infinity },
        { longitude: Infinity },
        { longitude: -Infinity },
        { latitude: -90.0001 },
        { latitude: 90.0001 },
        { longitude: -180.0001 },
        { longitude: 180.0001 },
    ])('returns null for invalid coordinates %j', (overrides) => {
        const vehicle = makeVehicle(overrides);

        const coordinates = vehicleCoordinates(vehicle);

        expect(coordinates).toBeNull();
    });
});

describe('vehicleStatus', () => {
    it.each([
        ['2026-10-09T00:00:00.000Z', 'recent'],
        ['2026-10-08T23:59:00.001Z', 'recent'],
        ['2026-10-08T23:59:00.000Z', 'recent'],
        ['2026-10-08T23:58:59.999Z', 'stale'],
        ['2026-10-09T00:00:00.001Z', 'stale'],
        ['2026-10-09T07:59:30.000+08:00', 'recent'],
        [null, 'stale'],
        ['', 'stale'],
        ['not-a-date', 'stale'],
    ])('classifies timestamp %s as %s', (recordedAt, expected) => {
        const vehicle = makeVehicle({ recordedAt });

        const status = vehicleStatus(vehicle, now, freshnessSeconds);

        expect(status).toBe(expected);
    });

    it.each([null, 'not-a-date', '2026-10-08T23:59:30.000Z'])(
        'prioritizes invalid coordinates over timestamp %s',
        (recordedAt) => {
            const vehicle = makeVehicle({ latitude: 91, recordedAt });

            const status = vehicleStatus(vehicle, now, freshnessSeconds);

            expect(status).toBe('no-location');
        },
    );

    it('accepts zero coordinates as a recent location', () => {
        const vehicle = makeVehicle({ latitude: 0, longitude: 0 });

        const status = vehicleStatus(vehicle, now, freshnessSeconds);

        expect(status).toBe('recent');
    });

    it.each([
        ['2026-10-09T00:00:00.000Z', 'recent'],
        ['2026-10-08T23:59:59.999Z', 'stale'],
    ])(
        'uses a zero freshness window for timestamp %s',
        (recordedAt, expected) => {
            const vehicle = makeVehicle({ recordedAt });

            const status = vehicleStatus(vehicle, now, 0);

            expect(status).toBe(expected);
        },
    );
});

describe('filterVehicles', () => {
    it.each(['  aBc  ', '  MARIKINA ', '  jUaN  ', ' sAnToS '])(
        'matches trimmed case-insensitive plate, route, or driver search %s',
        (search) => {
            const matching = makeVehicle();
            const unrelated = makeVehicle({
                id: 'vehicle-2',
                plateNumber: 'XYZ 789',
                routeName: 'Quiapo - Baclaran',
                plannedDriver: 'Pedro Reyes',
                actualDriver: null,
            });

            const result = filterVehicles(
                [unrelated, matching],
                search,
                'all',
                now,
                freshnessSeconds,
            );

            expect(result).toEqual([matching]);
        },
    );

    it('returns all vehicles in input order for whitespace search and all statuses', () => {
        const vehicles = [
            makeVehicle({ actualDriver: null }),
            makeVehicle({ id: 'stale', recordedAt: null }),
            makeVehicle({ id: 'no-location', longitude: null }),
        ];
        const original = structuredClone(vehicles);

        const result = filterVehicles(
            vehicles,
            '   ',
            'all',
            now,
            freshnessSeconds,
        );

        expect(result).toEqual(original);
        expect(vehicles).toEqual(original);
        expect(result).not.toBe(vehicles);
    });

    it.each(['recent', 'stale', 'no-location'] as const)(
        'combines search with the %s status filter',
        (status) => {
            const vehicles = [
                makeVehicle({ id: 'recent' }),
                makeVehicle({ id: 'stale', recordedAt: null }),
                makeVehicle({ id: 'no-location', latitude: null }),
                makeVehicle({ id: 'unrelated', plateNumber: 'XYZ 789' }),
            ];
            const expected = vehicles.filter(
                (vehicle) => vehicle.id === status,
            );

            const result = filterVehicles(
                vehicles,
                'abc',
                status,
                now,
                freshnessSeconds,
            );

            expect(result).toEqual(expected);
        },
    );

    it('returns no vehicles when the search does not match', () => {
        const vehicles = [makeVehicle({ actualDriver: null })];

        const result = filterVehicles(
            vehicles,
            'missing',
            'all',
            now,
            freshnessSeconds,
        );

        expect(result).toEqual([]);
    });

    it('returns an empty result for an empty fleet', () => {
        const result = filterVehicles([], '', 'all', now, freshnessSeconds);

        expect(result).toEqual([]);
    });
});

describe('vehicleSpeed', () => {
    it.each([0, 32.5])(
        'returns recent finite nonnegative speed %s',
        (speedKph) => {
            const vehicle = makeVehicle({
                speedKph,
                recordedAt: '2026-10-08T23:59:00.000Z',
            });

            const speed = vehicleSpeed(vehicle, now, freshnessSeconds);

            expect(speed).toBe(speedKph);
        },
    );

    it.each([null, NaN, Infinity, -Infinity, -0.001])(
        'hides invalid speed %s even for a recent location',
        (speedKph) => {
            const vehicle = makeVehicle({ speedKph });

            const speed = vehicleSpeed(vehicle, now, freshnessSeconds);

            expect(speed).toBeNull();
        },
    );

    it.each([
        { recordedAt: '2026-10-08T23:58:59.999Z' },
        { recordedAt: '2026-10-09T00:00:00.001Z' },
        { recordedAt: null },
        { recordedAt: 'not-a-date' },
        { longitude: null },
        { latitude: 91 },
    ])('hides speed for unreliable tracking %j', (overrides) => {
        const vehicle = makeVehicle(overrides);

        const speed = vehicleSpeed(vehicle, now, freshnessSeconds);

        expect(speed).toBeNull();
    });
});

describe('formatRecordedAt', () => {
    it.each([null, '', 'not-a-date'])(
        'returns Not available for timestamp %s',
        (recordedAt) => {
            const result = formatRecordedAt(recordedAt, 'Asia/Manila');

            expect(result).toBe('Not available');
        },
    );

    it.each([
        ['2026-10-09T00:00:00.000Z', 'Asia/Manila', 'Oct 9, 2026, 8:00:00 AM'],
        ['2026-10-08T20:30:00.000Z', 'Asia/Manila', 'Oct 9, 2026, 4:30:00 AM'],
        [
            '2026-10-09T08:00:00.000+08:00',
            'Asia/Manila',
            'Oct 9, 2026, 8:00:00 AM',
        ],
        ['2026-10-09T00:00:00.000Z', 'UTC', 'Oct 9, 2026, 12:00:00 AM'],
    ])(
        'formats %s in %s using the en-PH date and time',
        (recordedAt, timezone, expected) => {
            const result = formatRecordedAt(recordedAt, timezone);

            expect(result).toBe(expected);
        },
    );
});
