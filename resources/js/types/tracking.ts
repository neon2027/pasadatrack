export type TrackedVehicle = {
    id: string;
    plateNumber: string;
    routeName: string;
    plannedDriver: string;
    actualDriver: string | null;
    isReliever: boolean;
    latitude: number | null;
    longitude: number | null;
    speedKph: number | null;
    recordedAt: string | null;
};

export type TrackingSnapshot = {
    mode: 'demo' | 'unconnected';
    referenceDevice: string;
    center: [number, number];
    timezone: string;
    freshnessSeconds: number;
    vehicles: TrackedVehicle[];
};
