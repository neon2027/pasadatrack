import type { Map as LeafletMap, Marker } from 'leaflet';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    formatRecordedAt,
    vehicleCoordinates,
    vehicleSpeed,
    vehicleStatus,
} from '@/lib/fleet-tracking';
import type { TrackedVehicle } from '@/types/tracking';

type Props = {
    center: [number, number];
    vehicles: TrackedVehicle[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    now: number;
    freshnessSeconds: number;
    timezone: string;
};

type MapRuntime = { leaflet: typeof import('leaflet'); map: LeafletMap };
type TileStatus = 'loading' | 'ready' | 'unavailable';

function createJeepneyIcon(): HTMLSpanElement {
    const wrapper = document.createElement('span');
    wrapper.setAttribute('aria-hidden', 'true');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 40 32');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    for (const shape of [
        'M5 10V7h23l2 3',
        'M4 10h24v8h6l3 4v3h-4M27 25H12M6 25H3V13l1-3Z',
        'M7 12h5v6H7zM15 12h5v6h-5zM23 12h5v6h-5z',
        'M4 21h24M31 21h5M35 24h4',
        'M12 25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'M33 25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    ]) {
        const path = document.createElementNS(
            'http://www.w3.org/2000/svg',
            'path',
        );
        path.setAttribute('d', shape);
        svg.append(path);
    }
    wrapper.append(svg);
    return wrapper;
}

function createVehiclePopover(
    vehicle: TrackedVehicle,
    now: number,
    freshnessSeconds: number,
    timezone: string,
): HTMLDivElement {
    const content = document.createElement('div');
    const title = document.createElement('strong');
    title.className = 'fleet-popover-title';
    title.textContent = vehicle.plateNumber;
    const status = document.createElement('p');
    status.className = 'fleet-popover-status';
    status.textContent =
        now === 0
            ? 'Checking timestamp…'
            : vehicleStatus(vehicle, now, freshnessSeconds) === 'recent'
              ? 'Recent sample location'
              : 'Stale location — not the current position';
    const details = document.createElement('dl');
    const speed = vehicleSpeed(vehicle, now, freshnessSeconds);
    for (const [label, value] of [
        ['Route', vehicle.routeName],
        ['Planned driver', vehicle.plannedDriver],
        [
            'Actual driver',
            `${vehicle.actualDriver ?? 'Not confirmed'}${vehicle.isReliever ? ' (reliever)' : ''}`,
        ],
        ['Sample speed', speed === null ? 'Not available' : `${speed} km/h`],
        [
            `Recorded (${timezone})`,
            formatRecordedAt(vehicle.recordedAt, timezone),
        ],
    ]) {
        const row = document.createElement('div');
        const term = document.createElement('dt');
        const description = document.createElement('dd');
        term.textContent = label;
        description.textContent = value;
        row.append(term, description);
        details.append(row);
    }
    content.append(title, status, details);
    return content;
}

export default function FleetMap({
    center,
    vehicles,
    selectedId,
    onSelect,
    now,
    freshnessSeconds,
    timezone,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const markers = useRef(new Map<string, Marker>());
    const tooltipTimers = useRef(
        new Map<string, ReturnType<typeof setTimeout>>(),
    );
    const [runtime, setRuntime] = useState<MapRuntime | null>(null);
    const [tileStatus, setTileStatus] = useState<TileStatus>('loading');
    const [mapFailed, setMapFailed] = useState(false);
    const [latitude, longitude] = center;

    useEffect(() => {
        let disposed = false;
        let map: LeafletMap | undefined;
        let observer: ResizeObserver | undefined;
        let loadingTimeout: ReturnType<typeof setTimeout> | undefined;

        // Leaflet accesses window; defer the runtime import until after hydration.
        void import('leaflet')
            .then((leaflet) => {
                if (disposed || !container.current) {
                    return;
                }

                map = leaflet.map(container.current, {
                    center: [latitude, longitude],
                    zoom: 14,
                    scrollWheelZoom: false,
                });
                const failedTiles = new Set<HTMLElement>();
                const tiles = leaflet.tileLayer(
                    'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        maxZoom: 19,
                        attribution:
                            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
                    },
                );
                const startLoading = () => {
                    setTileStatus(
                        failedTiles.size > 0 ? 'unavailable' : 'loading',
                    );
                    clearTimeout(loadingTimeout);
                    loadingTimeout = setTimeout(() => {
                        if (!disposed) {
                            setTileStatus('unavailable');
                        }
                    }, 15000);
                };
                tiles.on('loading', startLoading);
                tiles.on('tileerror', (event) => {
                    failedTiles.add(event.tile);
                    setTileStatus('unavailable');
                });
                const clearTileFailure = (
                    event: import('leaflet').TileEvent,
                ) => {
                    failedTiles.delete(event.tile);
                    if (!disposed && !tiles.isLoading()) {
                        setTileStatus(
                            failedTiles.size > 0 ? 'unavailable' : 'ready',
                        );
                    }
                };
                tiles.on('tileload', clearTileFailure);
                tiles.on('tileunload', clearTileFailure);
                tiles.on('load', () => {
                    clearTimeout(loadingTimeout);
                    setTileStatus(
                        failedTiles.size > 0 ? 'unavailable' : 'ready',
                    );
                });
                tiles.addTo(map);
                const activeMap = map;
                observer = new ResizeObserver(() => {
                    activeMap.invalidateSize({ pan: false });
                });
                observer.observe(container.current);
                setRuntime({ leaflet, map });
            })
            .catch(() => {
                if (!disposed) {
                    setMapFailed(true);
                }
            });

        return () => {
            disposed = true;
            clearTimeout(loadingTimeout);
            observer?.disconnect();
            map?.remove();
        };
    }, [latitude, longitude]);

    useEffect(() => {
        const currentMarkers = markers.current;
        const currentTimers = tooltipTimers.current;
        return () => {
            for (const timer of currentTimers.values()) {
                clearTimeout(timer);
            }
            currentTimers.clear();
            for (const marker of currentMarkers.values()) {
                marker.remove();
            }
            currentMarkers.clear();
        };
    }, [runtime]);

    useEffect(() => {
        if (!runtime) {
            return;
        }

        const { leaflet, map } = runtime;
        const visibleIds = new Set<string>();
        for (const vehicle of vehicles) {
            const coordinates = vehicleCoordinates(vehicle);
            if (!coordinates) {
                continue;
            }

            visibleIds.add(vehicle.id);
            let marker = markers.current.get(vehicle.id);
            if (!marker) {
                const markerElement = createJeepneyIcon();
                marker = leaflet
                    .marker(coordinates, {
                        icon: leaflet.divIcon({
                            className: 'fleet-marker-wrapper',
                            html: markerElement,
                            iconSize: [44, 44],
                            iconAnchor: [22, 22],
                        }),
                        title: '',
                        alt: vehicle.plateNumber,
                        keyboard: true,
                    })
                    .addTo(map);
                markers.current.set(vehicle.id, marker);
            }

            // Update in place so selection and clock ticks retain keyboard focus.
            marker.setLatLng(coordinates);
            const element = marker.getElement();
            const label = element?.firstElementChild;
            if (label) {
                label.className = `fleet-marker fleet-marker--${vehicleStatus(vehicle, now, freshnessSeconds)}${vehicle.id === selectedId ? ' fleet-marker--selected' : ''}`;
            }
            element?.setAttribute(
                'aria-pressed',
                String(vehicle.id === selectedId),
            );
            element?.setAttribute(
                'aria-label',
                `View details for ${vehicle.plateNumber}`,
            );
            const tooltip = createVehiclePopover(
                vehicle,
                now,
                freshnessSeconds,
                timezone,
            );
            if (marker.getTooltip()) {
                marker.setTooltipContent(tooltip);
            } else {
                marker.bindTooltip(tooltip, {
                    className: 'fleet-vehicle-popover',
                    direction: 'auto',
                    offset: [22, 0],
                    opacity: 1,
                    interactive: true,
                });
            }
            const activeMarker = marker;
            const cancelClose = () => {
                clearTimeout(tooltipTimers.current.get(vehicle.id));
                tooltipTimers.current.delete(vehicle.id);
            };
            const scheduleClose = () => {
                cancelClose();
                tooltipTimers.current.set(
                    vehicle.id,
                    setTimeout(() => {
                        activeMarker.closeTooltip();
                        tooltipTimers.current.delete(vehicle.id);
                    }, 200),
                );
            };
            marker.off('mouseover').on('mouseover', () => {
                cancelClose();
                activeMarker.openTooltip();
            });
            marker.off('mouseout').on('mouseout', scheduleClose);
            marker.off('tooltipopen').on('tooltipopen', () => {
                const popover = activeMarker.getTooltip()?.getElement();
                if (popover) {
                    popover.onmouseenter = cancelClose;
                    popover.onmouseleave = scheduleClose;
                }
            });
            if (element) {
                element.onkeydown = (event) => {
                    if (event.key === 'Escape') {
                        cancelClose();
                        activeMarker.closeTooltip();
                    }
                };
            }
            marker.off('click').on('click', () => {
                cancelClose();
                onSelect(vehicle.id);
                activeMarker.openTooltip();
            });
        }
        for (const [id, marker] of markers.current) {
            if (!visibleIds.has(id)) {
                clearTimeout(tooltipTimers.current.get(id));
                tooltipTimers.current.delete(id);
                marker.remove();
                markers.current.delete(id);
            }
        }
    }, [
        runtime,
        vehicles,
        selectedId,
        onSelect,
        now,
        freshnessSeconds,
        timezone,
    ]);

    const selectedVehicle = vehicles.find(
        (vehicle) => vehicle.id === selectedId,
    );
    const selectedCoordinates = selectedVehicle
        ? vehicleCoordinates(selectedVehicle)
        : null;
    const selectedLatitude = selectedCoordinates?.[0];
    const selectedLongitude = selectedCoordinates?.[1];

    useEffect(() => {
        if (
            runtime &&
            selectedLatitude !== undefined &&
            selectedLongitude !== undefined
        ) {
            runtime.map.setView([selectedLatitude, selectedLongitude], 15, {
                animate: false,
            });
        }
    }, [runtime, selectedLatitude, selectedLongitude]);

    function fitFleet(): void {
        if (!runtime) {
            return;
        }
        const coordinates = vehicles.flatMap((vehicle) => {
            const point = vehicleCoordinates(vehicle);
            return point ? [point] : [];
        });
        if (coordinates.length > 0) {
            runtime.map.fitBounds(coordinates, {
                padding: [60, 60],
                maxZoom: 15,
                animate: false,
            });
        }
    }

    return (
        <div className="fleet-map relative isolate min-h-[60vh] overflow-hidden bg-muted lg:min-h-[70vh]">
            <div
                ref={container}
                className="absolute inset-0"
                role="region"
                aria-label="Demo jeepney map. Vehicle details are also available in the fleet list."
            />
            <div className="absolute top-3 right-3 z-1000">
                <Button
                    variant="secondary"
                    className="min-h-11 shadow-sm"
                    onClick={fitFleet}
                    disabled={
                        !runtime ||
                        mapFailed ||
                        !vehicles.some((vehicle) => vehicleCoordinates(vehicle))
                    }
                >
                    Fit visible fleet
                </Button>
            </div>
            {(mapFailed || tileStatus !== 'ready') && (
                <div
                    className="absolute right-3 bottom-10 left-3 z-1000 rounded-md border bg-background px-3 py-2 text-sm shadow-sm"
                    role="status"
                >
                    {mapFailed
                        ? 'Map could not start. Use the fleet list to view vehicle details.'
                        : tileStatus === 'loading'
                          ? 'Loading map tiles…'
                          : 'Some map tiles are unavailable. Check your connection; vehicle details are still available in the fleet list.'}
                </div>
            )}
        </div>
    );
}
