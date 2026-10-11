import { Head } from '@inertiajs/react';
import { MapPin, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import FleetMap from '@/components/fleet-map';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { useOnboarding } from '@/components/workspace-onboarding';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    filterVehicles,
    formatRecordedAt,
    vehicleSpeed,
    vehicleStatus,
} from '@/lib/fleet-tracking';
import type { FleetStatus } from '@/lib/fleet-tracking';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';
import type { TrackingSnapshot } from '@/types/tracking';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    tracking: TrackingSnapshot;
};

const statusLabels: Record<FleetStatus, string> = {
    recent: 'Recent location',
    stale: 'Stale location',
    'no-location': 'No location',
};

export default function Dashboard({
    pendingInvitations = [],
    tracking,
}: Props) {
    const { startAutomatically } = useOnboarding();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<FleetStatus | 'all'>('all');
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [now, setNow] = useState(0);

    useEffect(() => {
        if (!showInvitations) {
            startAutomatically();
        }
    }, [showInvitations, startAutomatically]);

    useEffect(() => {
        setNow(Date.now());
        // Age the fixed snapshot rather than pretending to receive new GPS fixes.
        const interval = setInterval(() => setNow(Date.now()), 15000);
        return () => clearInterval(interval);
    }, []);

    const isDemo = tracking.mode === 'demo';
    const vehicles = isDemo
        ? filterVehicles(
              tracking.vehicles,
              search,
              status,
              now,
              tracking.freshnessSeconds,
          )
        : [];
    const selected = vehicles.find((vehicle) => vehicle.id === selectedId);
    const speed = selected
        ? vehicleSpeed(selected, now, tracking.freshnessSeconds)
        : null;

    return (
        <>
            <Head title="Fleet map" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Fleet map
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Jeepney locations and driver details in one view.
                        </p>
                    </div>
                    <span className="rounded-md border px-3 py-2 text-xs text-muted-foreground">
                        Reference device: {tracking.referenceDevice}
                    </span>
                </header>

                <div
                    className="rounded-lg border bg-muted/50 px-4 py-3 text-sm"
                    role="note"
                >
                    <p className="font-medium">
                        {isDemo
                            ? 'Demo mode — no GPS device connected'
                            : 'GPS tracking is not connected'}
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        {isDemo
                            ? 'Synthetic jeepneys in Manila. This is a fixed sample snapshot, not live tracking; locations do not move or refresh automatically.'
                            : 'No real vehicle locations are available yet. Device onboarding and a verified tracking integration are required.'}
                    </p>
                </div>

                {isDemo ? (
                    <div className="grid min-w-0 overflow-hidden rounded-lg border lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <FleetMap
                            center={tracking.center}
                            vehicles={vehicles}
                            selectedId={selected?.id ?? null}
                            onSelect={setSelectedId}
                            now={now}
                            freshnessSeconds={tracking.freshnessSeconds}
                            timezone={tracking.timezone}
                        />
                        <aside
                            className="flex min-w-0 flex-col border-t bg-background lg:border-t-0 lg:border-l"
                            aria-label="Fleet list and vehicle details"
                        >
                            <div className="space-y-3 border-b p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <h2 className="font-semibold">
                                        Demo fleet
                                    </h2>
                                    <span className="text-sm text-muted-foreground">
                                        {vehicles.length} of{' '}
                                        {tracking.vehicles.length}
                                    </span>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="fleet-search">
                                        Search fleet
                                    </Label>
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="fleet-search"
                                            type="search"
                                            placeholder="Plate, route or driver"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            className="min-h-11 pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="fleet-status">
                                        Location status
                                    </Label>
                                    <select
                                        id="fleet-status"
                                        value={status}
                                        onChange={(event) =>
                                            setStatus(
                                                event.target.value as
                                                    | FleetStatus
                                                    | 'all',
                                            )
                                        }
                                        className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="all">
                                            All statuses
                                        </option>
                                        <option value="recent">
                                            Recent location
                                        </option>
                                        <option value="stale">
                                            Stale location
                                        </option>
                                        <option value="no-location">
                                            No location
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <ul
                                className="max-h-72 divide-y overflow-y-auto"
                                aria-label="Jeepneys"
                            >
                                {vehicles.map((vehicle) => {
                                    const locationStatus = vehicleStatus(
                                        vehicle,
                                        now,
                                        tracking.freshnessSeconds,
                                    );
                                    return (
                                        <li key={vehicle.id}>
                                            <button
                                                type="button"
                                                aria-pressed={
                                                    selected?.id === vehicle.id
                                                }
                                                onClick={() =>
                                                    setSelectedId(vehicle.id)
                                                }
                                                className={cn(
                                                    'w-full p-4 text-left hover:bg-muted focus-visible:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                                                    selected?.id ===
                                                        vehicle.id &&
                                                        'bg-muted',
                                                )}
                                            >
                                                <span className="flex items-center justify-between gap-2">
                                                    <span className="font-medium">
                                                        {vehicle.plateNumber}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'rounded px-2 py-1 text-xs',
                                                            locationStatus ===
                                                                'recent'
                                                                ? 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200'
                                                                : 'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        {now === 0
                                                            ? 'Checking…'
                                                            : statusLabels[
                                                                  locationStatus
                                                              ]}
                                                    </span>
                                                </span>
                                                <span className="mt-1 block text-sm text-muted-foreground">
                                                    {vehicle.routeName}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {vehicle.actualDriver ??
                                                        'Actual driver not confirmed'}
                                                    {vehicle.isReliever
                                                        ? ' · Reliever'
                                                        : ''}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                            {vehicles.length === 0 && (
                                <p
                                    className="p-4 text-sm text-muted-foreground"
                                    role="status"
                                >
                                    No jeepneys match your search and filter.
                                </p>
                            )}
                            <div className="border-t p-4" aria-live="polite">
                                {selected ? (
                                    <>
                                        <h2 className="font-semibold">
                                            {selected.plateNumber}
                                        </h2>
                                        <dl className="mt-3 space-y-3 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Route
                                                </dt>
                                                <dd>{selected.routeName}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Planned driver
                                                </dt>
                                                <dd>
                                                    {selected.plannedDriver}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Actual driver
                                                </dt>
                                                <dd>
                                                    {selected.actualDriver ??
                                                        'Not confirmed'}
                                                    {selected.isReliever
                                                        ? ' (reliever)'
                                                        : ''}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Sample recorded at (
                                                    {tracking.timezone})
                                                </dt>
                                                <dd>
                                                    {formatRecordedAt(
                                                        selected.recordedAt,
                                                        tracking.timezone,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Speed at sample time
                                                </dt>
                                                <dd>
                                                    {speed === null
                                                        ? 'Not available for a recent location'
                                                        : `${speed} km/h`}
                                                </dd>
                                            </div>
                                        </dl>
                                        {vehicleStatus(
                                            selected,
                                            now,
                                            tracking.freshnessSeconds,
                                        ) === 'stale' && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                This is an old location, not the
                                                jeepney’s current position.
                                            </p>
                                        )}
                                        {vehicleStatus(
                                            selected,
                                            now,
                                            tracking.freshnessSeconds,
                                        ) === 'no-location' && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                No valid coordinates; this
                                                jeepney is not shown on the map.
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Select a jeepney on the map or in the
                                        list to view its details.
                                    </p>
                                )}
                            </div>
                        </aside>
                    </div>
                ) : (
                    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 rounded-lg border bg-muted/30 p-6 text-center">
                        <MapPin
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="font-semibold">No GPS locations yet</h2>
                        <p className="max-w-md text-sm text-muted-foreground">
                            The fleet map will show verified locations after
                            trackers are connected. Demo markers are never shown
                            outside local development and testing.
                        </p>
                    </div>
                )}
                <p className="text-xs text-muted-foreground">
                    {isDemo &&
                        'Map tiles require internet and are provided by OpenStreetMap. '}
                    GPS locations do not confirm driving, record attendance, or
                    create boundary payments.
                </p>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Fleet map',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
