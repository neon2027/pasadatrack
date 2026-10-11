<?php

declare(strict_types=1);

namespace App\Data\Tracking;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Synthetic display samples only, not Teltonika payloads or operational records.
 *
 * @phpstan-type DemoVehicle array{
 *     id: string,
 *     plateNumber: string,
 *     routeName: string,
 *     plannedDriver: string,
 *     actualDriver: string|null,
 *     isReliever: bool,
 *     latitude: float|null,
 *     longitude: float|null,
 *     speedKph: int|null,
 *     recordedAt: string|null
 * }
 * @phpstan-type TrackingSnapshot array{
 *     mode: 'demo'|'unconnected',
 *     referenceDevice: 'Teltonika FMC920',
 *     center: array{float, float},
 *     timezone: 'Asia/Manila',
 *     freshnessSeconds: 120,
 *     vehicles: list<DemoVehicle>
 * }
 */
final class DemoFleet
{
    /**
     * @return TrackingSnapshot
     */
    public static function snapshot(): array
    {
        $isDemo = in_array(Config::string('app.env'), ['local', 'testing'], true)
            && Config::boolean('tracking.demo');

        return [
            'mode' => $isDemo ? 'demo' : 'unconnected',
            'referenceDevice' => 'Teltonika FMC920',
            'center' => [14.5995, 120.9842],
            'timezone' => 'Asia/Manila',
            'freshnessSeconds' => 120,
            'vehicles' => $isDemo ? self::vehicles(CarbonImmutable::now('UTC')) : [],
        ];
    }

    /**
     * @return list<DemoVehicle>
     */
    private static function vehicles(CarbonImmutable $now): array
    {
        return [
            [
                'id' => 'demo-01',
                'plateNumber' => 'DEMO 001',
                'routeName' => 'Sample Cubao - Quiapo',
                'plannedDriver' => 'Sample Driver 01',
                'actualDriver' => 'Sample Driver 01',
                'isReliever' => false,
                'latitude' => 14.6042,
                'longitude' => 120.9891,
                'speedKph' => 22,
                'recordedAt' => $now->subSeconds(30)->toIso8601ZuluString(),
            ],
            [
                'id' => 'demo-02',
                'plateNumber' => 'DEMO 002',
                'routeName' => 'Sample Divisoria - Taft',
                'plannedDriver' => 'Sample Driver 02',
                'actualDriver' => 'Sample Reliever 02',
                'isReliever' => true,
                'latitude' => 14.5983,
                'longitude' => 120.9797,
                'speedKph' => 0,
                'recordedAt' => $now->subSeconds(60)->toIso8601ZuluString(),
            ],
            [
                'id' => 'demo-03',
                'plateNumber' => 'DEMO 003',
                'routeName' => 'Sample Espana - Morayta',
                'plannedDriver' => 'Sample Driver 03',
                'actualDriver' => 'Sample Driver 03',
                'isReliever' => false,
                'latitude' => 14.6091,
                'longitude' => 120.9954,
                'speedKph' => null,
                'recordedAt' => $now->subMinutes(20)->toIso8601ZuluString(),
            ],
            [
                'id' => 'demo-04',
                'plateNumber' => 'DEMO 004',
                'routeName' => 'Sample Pandacan - Santa Cruz',
                'plannedDriver' => 'Sample Driver 04',
                'actualDriver' => null,
                'isReliever' => false,
                'latitude' => null,
                'longitude' => null,
                'speedKph' => null,
                'recordedAt' => null,
            ],
        ];
    }
}
