import { usePage } from '@inertiajs/react';
import { Check, Pencil, RotateCcw, X } from 'lucide-react';
import { choices, Filters, localTime, OperationsForm, Pagination, RecordTable, Status, Workspace, pesos } from '@/components/operations-workspace';
import type { Field, PageData } from '@/components/operations-workspace';
import { Button } from '@/components/ui/button';
import * as assignments from '@/routes/assignments';
import * as unavailabilities from '@/routes/unavailabilities';

type Shift = { id: number; planned_driver_id: number; jeepney_id: number; route_id: number; starts_at: string; ends_at: string; date: string; status: string; driver: string; plate: string; route: string; actual_driver: string | null; boundary: string | null; route_boundary: string };
type Block = { id: number; plate: string; starts_at: string; ends_at: string; reason: string };
type Props = { assignments: PageData<Shift>; unavailabilities: PageData<Block>; drivers: { id: number; name: string }[]; jeepneys: { id: number; plate_number: string }[]; routes: { id: number; name: string }[]; filters: { date?: string; status?: string }; canManage: boolean };
const times: Field[] = [{ name: 'starts_at', label: 'Starts at (Manila)', type: 'datetime-local', required: true }, { name: 'ends_at', label: 'Ends at (Manila)', type: 'datetime-local', required: true }];

export default function Scheduling(props: Props) {
    const team = usePage().props.currentTeam!.slug;
    const vehicleChoices = props.jeepneys.map((vehicle) => ({ value: String(vehicle.id), label: vehicle.plate_number }));
    const fields: Field[] = [{ name: 'planned_driver_id', label: 'Planned driver', options: choices(props.drivers), required: true }, { name: 'jeepney_id', label: 'Jeepney', options: vehicleChoices, required: true }, { name: 'route_id', label: 'Route', options: choices(props.routes), required: true }, ...times];
    const canSchedule = props.canManage && props.drivers.length > 0 && props.jeepneys.length > 0 && props.routes.length > 0;
    return <Workspace title="Scheduling" description="Plan shifts, record substitutes, and confirm who actually drove. All times use Asia/Manila." action={canSchedule && <OperationsForm title="Schedule shift" description="A scheduled shift does not create a boundary obligation until you confirm actual driving." fields={fields} action={assignments.store(team)} saveLabel="Schedule shift" />}>
        {!canSchedule && props.canManage && <p className="rounded-md border p-4 text-sm">Add an active driver, jeepney, and route before scheduling shifts.</p>}
        <Filters action={assignments.index.url(team)} fields={[{ name: 'date', label: 'Driving date', type: 'date' }, { name: 'status', label: 'Status', options: ['planned', 'confirmed', 'cancelled'].map((status) => ({ value: status, label: status })) }]} initial={props.filters} />
        <RecordTable headings={['Shift', 'Jeepney / route', 'Driver', 'Status', 'Actions']} count={props.assignments.data.length} empty="No shifts found. Schedule a shift, including an ad hoc shift for unscheduled driving, then confirm the actual driver.">
            {props.assignments.data.map((shift) => <tr key={shift.id}>
                <td><p className="font-medium">{localTime(shift.starts_at)}</p><p className="text-xs text-muted-foreground">Until {localTime(shift.ends_at)}</p></td>
                <td><p className="font-mono font-medium">{shift.plate}</p><p className="text-xs text-muted-foreground">{shift.route}</p></td>
                <td><p>{shift.actual_driver ?? shift.driver}</p>{shift.actual_driver && shift.actual_driver !== shift.driver && <p className="text-xs text-muted-foreground">Planned: {shift.driver}</p>}</td>
                <td><Status value={shift.status} />{shift.boundary && <p className="mt-1 text-xs tabular-nums">{pesos(shift.boundary)}</p>}</td>
                <td>{props.canManage && shift.status === 'planned' && <div className="flex flex-wrap gap-1">
                    <OperationsForm key={JSON.stringify(shift)} title="Edit shift" description="Changes must keep the driver and jeepney available for the entire shift." fields={fields} initial={shift} action={assignments.update.patch({ current_team: team, assignment: shift.id })} trigger={<Button size="sm" variant="ghost"><Pencil /> Edit</Button>} />
                    <OperationsForm title="Confirm driving" description={`Confirm actual driving for ${shift.plate} on ${shift.date}. This permanently records the actual driver and the displayed boundary amount.`} fields={[{ name: 'actual_driver_id', label: 'Actual driver', options: choices(props.drivers), required: true }, { name: 'expected_boundary_amount', label: 'Boundary to record (PHP)', type: 'money', readOnly: true }]} initial={{ actual_driver_id: shift.planned_driver_id, expected_boundary_amount: shift.route_boundary }} action={assignments.confirm({ current_team: team, assignment: shift.id })} saveLabel="Confirm driving" review trigger={<Button size="sm" variant="outline"><Check /> Confirm</Button>} />
                    <OperationsForm title="Cancel shift" description={`Cancel the planned shift for ${shift.plate}. Cancelled shifts cannot be confirmed.`} fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]} action={assignments.cancel({ current_team: team, assignment: shift.id })} saveLabel="Cancel shift" review trigger={<Button size="sm" variant="ghost"><X /> Cancel</Button>} />
                </div>}</td>
            </tr>)}
        </RecordTable><Pagination page={props.assignments} />
        <section className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4"><div><h2 className="font-semibold">Vehicle availability</h2><p className="text-sm text-muted-foreground">Block maintenance and other periods when a jeepney cannot be assigned.</p></div>
                {props.canManage && props.jeepneys.length > 0 && <OperationsForm title="Mark unavailable" description="Reschedule or cancel overlapping shifts before blocking a jeepney." fields={[{ name: 'jeepney_id', label: 'Jeepney', options: vehicleChoices, required: true }, ...times, { name: 'reason', label: 'Reason', type: 'textarea', required: true }]} action={unavailabilities.store(team)} saveLabel="Mark unavailable" />}
            </div>
            <RecordTable headings={['Jeepney', 'From', 'Until', 'Reason', 'Actions']} count={props.unavailabilities.data.length} empty="No vehicle unavailability recorded.">
                {props.unavailabilities.data.map((block) => <tr key={block.id}><td className="font-mono">{block.plate}</td><td>{localTime(block.starts_at)}</td><td>{localTime(block.ends_at)}</td><td>{block.reason}</td><td>{props.canManage && <OperationsForm title="Restore availability" description={`Remove the unavailable period for ${block.plate}. The change will remain in audit history.`} fields={[]} action={unavailabilities.destroy({ current_team: team, unavailability: block.id })} saveLabel="Restore availability" review trigger={<Button size="sm" variant="ghost"><RotateCcw /> Restore</Button>} />}</td></tr>)}
            </RecordTable><Pagination page={props.unavailabilities} />
        </section>
    </Workspace>;
}
