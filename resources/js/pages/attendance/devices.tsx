import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Fingerprint, Import, Pencil, RefreshCw, UserX } from 'lucide-react';
import { choices, localTime, OperationsForm, Pagination, RecordTable, Status, Workspace } from '@/components/operations-workspace';
import type { Field, PageData } from '@/components/operations-workspace';
import { Button } from '@/components/ui/button';
import * as attendance from '@/routes/attendance';
import * as devices from '@/routes/devices';

type Device = { id: number; code: string; name: string; is_active: boolean; device_users: { id: number; external_user_id: string; revoked_at: string | null; driver: { name: string } }[] };
type Event = { id: number; device: string; event_key: string; external_user_id: string; occurred_at: string; direction: string; processed: boolean };
type Props = { devices: PageData<Device>; drivers: { id: number; name: string }[]; events: PageData<Event> };
const fields: Field[] = [{ name: 'code', label: 'Device code', required: true }, { name: 'name', label: 'Device name', required: true }, { name: 'is_active', label: 'Active', type: 'checkbox', help: 'Inactive devices cannot import or process scans.' }];
export default function Devices(props: Props) {
    const team = usePage().props.currentTeam!.slug;
    return <Workspace title="Attendance devices & scans" description="Register devices, match device user IDs to drivers, and import normalized scans. Direct hardware connectivity requires the device’s supported data format." action={<OperationsForm title="Register device" description="Register an attendance terminal before matching its user IDs." fields={fields} action={devices.store(team)} saveLabel="Register device" />}>
        <Button variant="ghost" className="w-fit" asChild><Link href={attendance.index(team)}><ArrowLeft /> Back to attendance</Link></Button>
        <RecordTable headings={['Device', 'Status', 'Matched users', 'Actions']} count={props.devices.data.length} empty="Register your first attendance device, or use manual attendance while hardware is unavailable.">
            {props.devices.data.map((device) => <tr key={device.id}><td><p className="font-medium">{device.name}</p><p className="font-mono text-xs text-muted-foreground">{device.code}</p></td><td><Status value={device.is_active ? 'active' : 'inactive'} /></td>
                <td>{device.device_users.length ? <details><summary className="cursor-pointer">{device.device_users.length} user(s)</summary><ul className="mt-2 grid gap-2 text-xs">{device.device_users.map((user) => <li key={user.id} className="flex flex-wrap items-center gap-2"><span>{user.external_user_id} → {user.driver.name}</span>{user.revoked_at ? <Status value="revoked" /> : <OperationsForm title="Revoke match" description={`Stop future scans for user ${user.external_user_id} from matching ${user.driver.name}. Historical attendance stays intact. This revokes the app’s match; remove the user from the terminal separately when needed.`} fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]} action={devices.revoke({ current_team: team, device: device.id, enrollment: user.id })} saveLabel="Revoke match" review trigger={<Button variant="ghost" size="sm"><UserX /> Revoke</Button>} />}</li>)}</ul></details> : 'No users matched'}</td>
                <td><div className="flex flex-wrap gap-1">
                    <OperationsForm key={JSON.stringify(device)} title="Edit device" description="Device status controls scan import and processing." fields={fields} initial={{ code: device.code, name: device.name, is_active: device.is_active }} action={devices.update({ current_team: team, device: device.id })} trigger={<Button size="sm" variant="ghost"><Pencil /> Edit</Button>} />
                    {device.is_active && <>
                        <OperationsForm title="Match driver" description={`Match a user ID on ${device.name} to an active driver. Existing matches are preserved to protect attendance evidence.`} fields={[{ name: 'external_user_id', label: 'User ID on the device', required: true }, { name: 'driver_id', label: 'Driver', options: choices(props.drivers), required: true }]} action={devices.enroll({ current_team: team, device: device.id })} saveLabel="Match driver" trigger={<Button size="sm" variant="outline"><Fingerprint /> Match driver</Button>} />
                        <OperationsForm title="Import scans" description={`Import up to 500 normalized scan records for ${device.name}. Repeated event keys are deduplicated; unknown users and conflicting scans remain pending.`} fields={[{ name: 'events', label: 'Scan records (JSON array)', type: 'textarea', required: true, help: 'Each record needs event_key, external_user_id, occurred_at with a timezone offset, and direction (in or out).' }]} action={devices.importMethod({ current_team: team, device: device.id })} saveLabel="Import scans" transform={(data) => {
                            const events = JSON.parse(String(data.events)) as unknown;
                            if (!Array.isArray(events)) throw new Error('Enter a JSON array of scan records.');
                            return { events };
                        }} trigger={<Button size="sm" variant="outline"><Import /> Import scans</Button>} />
                        <OperationsForm title="Process pending scans" description={`Retry unmatched scans for ${device.name} after matching users. Conflicting or incomplete scans stay pending for manual review.`} fields={[]} action={devices.process({ current_team: team, device: device.id })} saveLabel="Process pending scans" trigger={<Button size="sm" variant="ghost"><RefreshCw /> Process pending</Button>} />
                    </>}
                </div></td>
            </tr>)}
        </RecordTable><Pagination page={props.devices} />
        <section className="grid gap-4"><div><h2 className="font-semibold">Scan evidence</h2><p className="text-sm text-muted-foreground">Pending scans need a user match or review of overlapping sessions, missing check-ins, or out-of-order data. Attendance corrections preserve the original scans.</p></div>
            <RecordTable headings={['Device', 'Event key', 'User ID', 'When (Manila)', 'Direction', 'Status']} count={props.events.data.length} empty="No scans imported. Manual attendance remains available.">
                {props.events.data.map((event) => <tr key={event.id}><td>{event.device}</td><td className="font-mono text-xs">{event.event_key}</td><td>{event.external_user_id}</td><td className="whitespace-nowrap">{localTime(event.occurred_at)}</td><td>{event.direction}</td><td><Status value={event.processed ? 'processed' : 'pending-review'} /></td></tr>)}
            </RecordTable><Pagination page={props.events} />
        </section>
    </Workspace>;
}
